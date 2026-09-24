import {ChangeDetectorRef, Component, inject} from '@angular/core';
import {DatePipe} from '@angular/common';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {
  MatchPlanifie,
  MembreLeger,
  ParticipantTournoi,
  TournoiArchive,
  TournoiDetail,
  TournoiListe,
  TournoisService,
} from '../services/tournois.service';
import {AuthService} from '../services/auth.service';

@Component({
  selector: 'app-tournois-component',
  standalone: true,
  imports: [DatePipe, ReactiveFormsModule],
  templateUrl: './tournois-component.html',
  styleUrl: './tournois-component.css',
})
export class TournoisComponent {
  private tournoisService = inject(TournoisService);
  private authService = inject(AuthService);
  private fb = inject(FormBuilder);
  private cdr = inject(ChangeDetectorRef);

  tournois: TournoiListe[] = [];
  tournoisArchive: TournoiArchive[] = [];
  membresDisponibles: MembreLeger[] = [];

  // Date du jour au format YYYY-MM-DD, pour empêcher de choisir une date
  // passée directement dans le sélecteur natif du navigateur.
  dateMin = new Date().toISOString().split('T')[0];

  constructor() {
    this.chargerTournois();
    this.chargerArchive();
    this.chargerMembresDisponibles();
  }

  estAdmin(): boolean {
    return this.authService.isAdmin();
  }

  private chargerTournois(): void {
    this.tournoisService.liste().subscribe({
      next: (data) => {
        this.tournois = data;
        this.cdr.detectChanges();
      }
    });
  }

  private chargerArchive(): void {
    this.tournoisService.listeAnnules().subscribe({
      next: (data) => {
        this.tournoisArchive = data;
        this.cdr.detectChanges();
      }
    });
  }

  // ---------- Détail d'un tournoi archivé (annulé ou terminé) ----------

  archiveSelectionnee: TournoiArchive | null = null;
  // Rempli seulement si le tournoi est "terminé" — contient le classement
  // final (participants + points), récupéré via la même route de détail
  // que pour un tournoi actif.
  archiveDetailComplet: TournoiDetail | null = null;

  ouvrirDetailArchive(t: TournoiArchive): void {
    this.archiveSelectionnee = t;
    this.archiveDetailComplet = null;

    if (t.statut === 'termine') {
      this.tournoisService.detail(t.id).subscribe({
        next: (data) => {
          this.archiveDetailComplet = data;
          this.cdr.detectChanges();
        }
      });
    }
  }

  fermerDetailArchive(): void {
    this.archiveSelectionnee = null;
    this.archiveDetailComplet = null;
  }

  // Classement trié du meilleur score au moins bon — pour l'affichage.
  get classementTrie(): ParticipantTournoi[] {
    if (!this.archiveDetailComplet) {
      return [];
    }
    return [...this.archiveDetailComplet.participants].sort(
      (a, b) => parseFloat(b.resultat ?? '0') - parseFloat(a.resultat ?? '0')
    );
  }

  // Même tri, mais pour la modale de détail d'un tournoi ACTIF (utile
  // quand il vient de passer automatiquement à "termine" pendant qu'on
  // avait la modale ouverte, sans avoir à la fermer/rouvrir depuis l'archive).
  get classementTrieActif(): ParticipantTournoi[] {
    if (!this.tournoiSelectionne) {
      return [];
    }
    return [...this.tournoiSelectionne.participants].sort(
      (a, b) => parseFloat(b.resultat ?? '0') - parseFloat(a.resultat ?? '0')
    );
  }

  private chargerMembresDisponibles(): void {
    this.tournoisService.membresDisponibles().subscribe({
      next: (data) => {
        this.membresDisponibles = data;
        this.cdr.detectChanges();
      }
    });
  }

  // ---------- Création d'un tournoi ----------

  modaleCreationOuverte = false;
  messagesErreursCreation: string[] = [];
  creationEnCours = false;

  creationForm: FormGroup = this.fb.group({
    nom: ['', Validators.required],
    date: ['', Validators.required],
  });

  ouvrirModaleCreation(): void {
    this.creationForm.reset();
    this.messagesErreursCreation = [];
    this.modaleCreationOuverte = true;
  }

  fermerModaleCreation(): void {
    this.modaleCreationOuverte = false;
  }

  creerTournoi(): void {
    if (this.creationEnCours) {
      return;
    }

    this.messagesErreursCreation = [];

    const messages: string[] = [];
    if (this.creationForm.get('nom')?.errors?.['required']) {
      messages.push('Remplissez le nom du tournoi.');
    }
    if (this.creationForm.get('date')?.errors?.['required']) {
      messages.push('Choisissez une date.');
    }
    if (messages.length > 0) {
      this.messagesErreursCreation = messages;
      this.cdr.detectChanges();
      return;
    }

    const {nom, date} = this.creationForm.value;

    if (date < this.dateMin) {
      this.messagesErreursCreation = ['La date du tournoi ne peut pas être dans le passé.'];
      this.cdr.detectChanges();
      return;
    }

    this.creationEnCours = true;

    this.tournoisService.creer(nom, date).subscribe({
      next: () => {
        this.creationEnCours = false;
        this.fermerModaleCreation();
        this.chargerTournois();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.creationEnCours = false;
        this.messagesErreursCreation = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Détail d'un tournoi ----------

  tournoiSelectionne: TournoiDetail | null = null;
  modeEditionInfos = false;
  messagesErreursEdition: string[] = [];
  editionEnCours = false;

  editionForm: FormGroup = this.fb.group({
    nom: ['', Validators.required],
    date: ['', Validators.required],
  });

  ouvrirDetail(tournoi: TournoiListe): void {
    this.tournoisService.detail(tournoi.id).subscribe({
      next: (data) => {
        this.tournoiSelectionne = data;
        this.modeEditionInfos = false;
        this.messageErreurParticipant = '';
        this.participantChoisi = '';
        this.cdr.detectChanges();

        if (data.statut === 'en_cours') {
          this.chargerMatchsPlanifies(tournoi.id);
        }
      }
    });
  }

  matchsPlanifies: MatchPlanifie[] = [];

  private chargerMatchsPlanifies(tournoiId: number): void {
    this.tournoisService.listeMatchs(tournoiId).subscribe({
      next: (data) => {
        this.matchsPlanifies = data;
        this.cdr.detectChanges();
      }
    });
  }

  fermerDetail(): void {
    this.tournoiSelectionne = null;
    this.modeEditionInfos = false;
    this.matchsPlanifies = [];
  }

  private rechargerDetail(): void {
    if (!this.tournoiSelectionne) {
      return;
    }
    this.tournoisService.detail(this.tournoiSelectionne.id).subscribe({
      next: (data) => {
        this.tournoiSelectionne = data;
        this.cdr.detectChanges();
      }
    });
  }

  // ---------- Modifier nom/date ----------

  basculerModeEdition(): void {
    if (!this.tournoiSelectionne) {
      return;
    }
    this.editionForm.setValue({
      nom: this.tournoiSelectionne.nom,
      date: this.tournoiSelectionne.date,
    });
    this.messagesErreursEdition = [];
    this.modeEditionInfos = true;
  }

  annulerEditionForm(): void {
    this.modeEditionInfos = false;
  }

  enregistrerEdition(): void {
    if (!this.tournoiSelectionne || this.editionEnCours) {
      return;
    }

    this.messagesErreursEdition = [];

    const messages: string[] = [];
    if (this.editionForm.get('nom')?.errors?.['required']) {
      messages.push('Remplissez le nom du tournoi.');
    }
    if (this.editionForm.get('date')?.errors?.['required']) {
      messages.push('Choisissez une date.');
    }
    if (messages.length > 0) {
      this.messagesErreursEdition = messages;
      this.cdr.detectChanges();
      return;
    }

    const {nom, date} = this.editionForm.value;

    if (date < this.dateMin) {
      this.messagesErreursEdition = ['La date du tournoi ne peut pas être dans le passé.'];
      this.cdr.detectChanges();
      return;
    }

    this.editionEnCours = true;

    this.tournoisService.modifier(this.tournoiSelectionne.id, nom, date).subscribe({
      next: () => {
        this.editionEnCours = false;
        this.modeEditionInfos = false;
        this.rechargerDetail();
        this.chargerTournois();
      },
      error: (err) => {
        this.editionEnCours = false;
        this.messagesErreursEdition = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Gestion des participants (avant lancement) ----------

  participantChoisi = '';
  messageErreurParticipant = '';

  get participantsDisponiblesPourAjout(): MembreLeger[] {
    if (!this.tournoiSelectionne) {
      return [];
    }
    const idsDejaInscrits = this.tournoiSelectionne.participants.map(p => p.utilisateur_id);
    return this.membresDisponibles.filter(m => !idsDejaInscrits.includes(m.id));
  }

  onParticipantChange(valeur: string): void {
    this.participantChoisi = valeur;
  }

  ajouterParticipant(): void {
    if (!this.tournoiSelectionne || !this.participantChoisi) {
      return;
    }

    this.messageErreurParticipant = '';

    this.tournoisService.inscrireParticipant(this.tournoiSelectionne.id, +this.participantChoisi).subscribe({
      next: () => {
        this.participantChoisi = '';
        this.rechargerDetail();
        this.chargerTournois();
      },
      error: (err) => {
        this.messageErreurParticipant = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      },
    });
  }

  confirmationRetraitEnCours: ParticipantTournoi | null = null;

  demanderConfirmationRetrait(participant: ParticipantTournoi): void {
    this.confirmationRetraitEnCours = participant;
  }

  annulerConfirmationRetrait(): void {
    this.confirmationRetraitEnCours = null;
  }

  confirmerRetrait(): void {
    if (!this.tournoiSelectionne || !this.confirmationRetraitEnCours) {
      return;
    }

    this.tournoisService.desinscrireParticipant(
      this.tournoiSelectionne.id,
      this.confirmationRetraitEnCours.utilisateur_id
    ).subscribe({
      next: () => {
        this.confirmationRetraitEnCours = null;
        this.rechargerDetail();
        this.chargerTournois();
      }
    });
  }

  // ---------- Lancement ----------

  confirmationLancementEnCours = false;

  demanderConfirmationLancement(): void {
    this.confirmationLancementEnCours = true;
  }

  annulerConfirmationLancement(): void {
    this.confirmationLancementEnCours = false;
  }

  confirmerLancement(): void {
    if (!this.tournoiSelectionne) {
      return;
    }

    this.tournoisService.lancer(this.tournoiSelectionne.id).subscribe({
      next: () => {
        this.confirmationLancementEnCours = false;
        this.rechargerDetail();
        this.chargerTournois();
      },
      error: (err) => {
        this.confirmationLancementEnCours = false;
        this.messageErreurParticipant = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Saisie des résultats de match ----------

  joueur1Choisi = '';
  joueur2Choisi = '';
  resultatChoisi: 'joueur1' | 'joueur2' | 'nul' | '' = '';
  messageErreurMatch = '';
  saisieMatchEnCours = false;

  onJoueur1Change(valeur: string): void {
    this.joueur1Choisi = valeur;
  }

  onJoueur2Change(valeur: string): void {
    this.joueur2Choisi = valeur;
  }

  onResultatChange(valeur: string): void {
    this.resultatChoisi = valeur as 'joueur1' | 'joueur2' | 'nul' | '';
  }

  saisirMatch(): void {
    if (!this.tournoiSelectionne || this.saisieMatchEnCours) {
      return;
    }

    this.messageErreurMatch = '';

    if (!this.joueur1Choisi || !this.joueur2Choisi || !this.resultatChoisi) {
      this.messageErreurMatch = 'Choisissez les 2 joueurs et un résultat.';
      this.cdr.detectChanges();
      return;
    }

    if (this.joueur1Choisi === this.joueur2Choisi) {
      this.messageErreurMatch = 'Choisissez deux joueurs différents.';
      this.cdr.detectChanges();
      return;
    }

    this.saisieMatchEnCours = true;

    this.tournoisService.saisirMatch(
      this.tournoiSelectionne.id,
      +this.joueur1Choisi,
      +this.joueur2Choisi,
      this.resultatChoisi
    ).subscribe({
      next: (data) => {
        this.saisieMatchEnCours = false;
        this.joueur1Choisi = '';
        this.joueur2Choisi = '';
        this.resultatChoisi = '';
        this.tournoiSelectionne = data;
        this.chargerMatchsPlanifies(data.id);
        if (data.statut === 'termine') {
          // Vient de se terminer tout seul (dernier match du round-robin
          // saisi) — on rafraîchit les listes en tâche de fond, pour que
          // tout soit déjà à jour au moment où l'admin ferme la modale.
          this.chargerTournois();
          this.chargerArchive();
        }
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.saisieMatchEnCours = false;
        this.messageErreurMatch = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Terminer le tournoi ----------

  confirmationTerminerEnCours = false;

  demanderConfirmationTerminer(): void {
    this.confirmationTerminerEnCours = true;
  }

  annulerConfirmationTerminer(): void {
    this.confirmationTerminerEnCours = false;
  }

  confirmerTerminer(): void {
    if (!this.tournoiSelectionne) {
      return;
    }

    this.tournoisService.terminerTournoi(this.tournoiSelectionne.id).subscribe({
      next: () => {
        this.confirmationTerminerEnCours = false;
        this.fermerDetail();
        this.chargerTournois();
        this.chargerArchive();
        this.cdr.detectChanges();
      }
    });
  }

  // ---------- Annulation du tournoi (avec motif, conservé en archive) ----------

  modaleAnnulationOuverte = false;
  motifAnnulation = '';
  messageErreurAnnulation = '';
  annulationEnCours = false;

  ouvrirModaleAnnulation(): void {
    this.motifAnnulation = '';
    this.messageErreurAnnulation = '';
    this.modaleAnnulationOuverte = true;
  }

  fermerModaleAnnulation(): void {
    this.modaleAnnulationOuverte = false;
  }

  onMotifAnnulationChange(valeur: string): void {
    this.motifAnnulation = valeur;
  }

  confirmerAnnulation(): void {
    if (!this.tournoiSelectionne || this.annulationEnCours) {
      return;
    }

    if (!this.motifAnnulation.trim()) {
      this.messageErreurAnnulation = "Indiquez le motif de l'annulation.";
      this.cdr.detectChanges();
      return;
    }

    this.messageErreurAnnulation = '';
    this.annulationEnCours = true;

    this.tournoisService.annuler(this.tournoiSelectionne.id, this.motifAnnulation.trim()).subscribe({
      next: () => {
        this.annulationEnCours = false;
        this.modaleAnnulationOuverte = false;
        this.fermerDetail();
        this.chargerTournois();
        this.chargerArchive();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.annulationEnCours = false;
        this.messageErreurAnnulation = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      },
    });
  }
}
