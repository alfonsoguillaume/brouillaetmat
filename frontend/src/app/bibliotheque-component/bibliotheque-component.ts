import {ChangeDetectorRef, Component, inject} from '@angular/core';
import {DatePipe} from '@angular/common';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {
  ArchiveEmpruntLigne,
  BibliothequeService,
  EmpruntEnCours,
  MembreLeger,
  Ouvrage,
} from '../services/bibliotheque.service';

@Component({
  selector: 'app-bibliotheque-component',
  standalone: true,
  imports: [DatePipe, ReactiveFormsModule],
  templateUrl: './bibliotheque-component.html',
  styleUrl: './bibliotheque-component.css',
})
export class BibliothequeComponent {
  private biblioService = inject(BibliothequeService);
  private fb = inject(FormBuilder);
  private cdr = inject(ChangeDetectorRef);

  ouvrages: Ouvrage[] = [];
  membres: MembreLeger[] = [];
  emprunts: EmpruntEnCours[] = [];
  archives: ArchiveEmpruntLigne[] = [];

  constructor() {
    this.chargerTout();
  }

  private chargerTout(): void {
    this.chargerOuvrages();
    this.chargerMembres();
    this.chargerEmprunts();
    this.chargerArchives();
  }

  private chargerOuvrages(): void {
    this.biblioService.listeOuvrages().subscribe({
      next: (data) => {
        this.ouvrages = data;
        this.cdr.detectChanges();
      }
    });
  }

  private chargerMembres(): void {
    this.biblioService.listeMembres().subscribe({
      next: (data) => {
        this.membres = data;
        this.cdr.detectChanges();
      }
    });
  }

  private chargerEmprunts(): void {
    this.biblioService.listeEmprunts().subscribe({
      next: (data) => {
        this.emprunts = data;
        this.cdr.detectChanges();
      }
    });
  }

  private chargerArchives(): void {
    this.biblioService.listeArchives().subscribe({
      next: (data) => {
        this.archives = data;
        this.cdr.detectChanges();
      }
    });
  }

  get ouvragesDisponibles(): Ouvrage[] {
    return this.ouvrages.filter(o => o.disponible);
  }

  // ---------- Recherche dans le catalogue ----------

  filtreCatalogue = '';

  onFiltreCatalogueChange(valeur: string): void {
    this.filtreCatalogue = valeur;
  }

  get ouvragesFiltres(): Ouvrage[] {
    const recherche = this.filtreCatalogue.trim().toLowerCase();
    if (!recherche) {
      return this.ouvrages;
    }
    return this.ouvrages.filter(o =>
      o.titre.toLowerCase().includes(recherche) || o.auteur.toLowerCase().includes(recherche)
    );
  }

  // ---------- Modale ajout / modification d'ouvrage ----------

  modaleOuvrageOuverte = false;
  ouvrageEnEdition: Ouvrage | null = null;
  messagesErreursOuvrage: string[] = [];
  enregistrementEnCours = false;

  ouvrageForm: FormGroup = this.fb.group({
    titre: ['', [Validators.required, Validators.maxLength(255)]],
    auteur: ['', [Validators.required, Validators.maxLength(255)]],
  });

  ouvrirModaleAjoutOuvrage(): void {
    this.ouvrageEnEdition = null;
    this.ouvrageForm.reset();
    this.messagesErreursOuvrage = [];
    this.modaleOuvrageOuverte = true;
  }

  ouvrirModaleModifierOuvrage(ouvrage: Ouvrage): void {
    this.ouvrageEnEdition = ouvrage;
    this.ouvrageForm.setValue({titre: ouvrage.titre, auteur: ouvrage.auteur});
    this.messagesErreursOuvrage = [];
    this.modaleOuvrageOuverte = true;
  }

  fermerModaleOuvrage(): void {
    this.modaleOuvrageOuverte = false;
  }

  enregistrerOuvrage(): void {
    if (this.enregistrementEnCours) {
      return;
    }

    this.messagesErreursOuvrage = [];

    const messages: string[] = [];
    if (this.ouvrageForm.get('titre')?.errors?.['required']) {
      messages.push('Remplissez le titre.');
    } else if (this.ouvrageForm.get('titre')?.errors?.['maxlength']) {
      messages.push('Le titre est trop long (255 caractères maximum).');
    }
    if (this.ouvrageForm.get('auteur')?.errors?.['required']) {
      messages.push("Remplissez l'auteur.");
    } else if (this.ouvrageForm.get('auteur')?.errors?.['maxlength']) {
      messages.push("Le nom de l'auteur est trop long (255 caractères maximum).");
    }
    if (messages.length > 0) {
      this.messagesErreursOuvrage = messages;
      this.cdr.detectChanges();
      return;
    }

    const {titre, auteur} = this.ouvrageForm.value;
    this.enregistrementEnCours = true;

    const requete = this.ouvrageEnEdition
      ? this.biblioService.modifierOuvrage(this.ouvrageEnEdition.id, titre, auteur)
      : this.biblioService.ajouterOuvrage(titre, auteur);

    requete.subscribe({
      next: () => {
        this.enregistrementEnCours = false;
        this.fermerModaleOuvrage();
        this.chargerOuvrages();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.enregistrementEnCours = false;
        this.messagesErreursOuvrage = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Suppression d'un ouvrage ----------

  confirmationSuppressionOuvrage: Ouvrage | null = null;
  messageErreurSuppressionOuvrage = '';

  demanderConfirmationSuppressionOuvrage(ouvrage: Ouvrage): void {
    this.messageErreurSuppressionOuvrage = '';
    this.confirmationSuppressionOuvrage = ouvrage;
    this.modaleOuvrageOuverte = false;
  }

  annulerConfirmationSuppressionOuvrage(): void {
    this.confirmationSuppressionOuvrage = null;
  }

  confirmerSuppressionOuvrage(): void {
    if (!this.confirmationSuppressionOuvrage) {
      return;
    }

    this.biblioService.supprimerOuvrage(this.confirmationSuppressionOuvrage.id).subscribe({
      next: () => {
        this.confirmationSuppressionOuvrage = null;
        this.chargerOuvrages();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.messageErreurSuppressionOuvrage = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.confirmationSuppressionOuvrage = null;
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Nouvel emprunt ----------

  messageErreurEmprunt = '';
  empruntEnCours = false;

  empruntForm: FormGroup = this.fb.group({
    ouvrageId: ['', Validators.required],
    utilisateurId: ['', Validators.required],
  });

  creerEmprunt(): void {
    if (this.empruntEnCours) {
      return;
    }

    this.messageErreurEmprunt = '';

    if (!this.empruntForm.valid) {
      this.messageErreurEmprunt = 'Choisissez un ouvrage et un membre.';
      this.cdr.detectChanges();
      return;
    }

    const {ouvrageId, utilisateurId} = this.empruntForm.value;
    this.empruntEnCours = true;

    this.biblioService.creerEmprunt(+ouvrageId, +utilisateurId).subscribe({
      next: () => {
        this.empruntEnCours = false;
        this.empruntForm.reset();
        this.chargerOuvrages();
        this.chargerEmprunts();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.empruntEnCours = false;
        this.messageErreurEmprunt = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Marquer un retour ----------

  confirmationRetourEnCours: EmpruntEnCours | null = null;

  demanderConfirmationRetour(emprunt: EmpruntEnCours): void {
    this.confirmationRetourEnCours = emprunt;
  }

  annulerConfirmationRetour(): void {
    this.confirmationRetourEnCours = null;
  }

  confirmerRetour(): void {
    if (!this.confirmationRetourEnCours) {
      return;
    }

    this.biblioService.marquerRetour(this.confirmationRetourEnCours.id).subscribe({
      next: () => {
        this.confirmationRetourEnCours = null;
        this.chargerOuvrages();
        this.chargerEmprunts();
        this.chargerArchives();
        this.cdr.detectChanges();
      }
    });
  }
}
