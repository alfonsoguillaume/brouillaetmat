import {ChangeDetectorRef, Component, inject, OnDestroy, OnInit} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {ClassementEntry, JeuService, MembreJeu, PartieActive} from '../services/jeu.service';
import {PlateauComponent} from '../plateau-component/plateau-component';

@Component({
  selector: 'app-jouer-component',
  standalone: true,
  imports: [FormsModule, PlateauComponent],
  templateUrl: './jouer-component.html',
  styleUrl: './jouer-component.css',
})
export class JouerComponent implements OnInit, OnDestroy {
  private jeuService = inject(JeuService);
  private cdr = inject(ChangeDetectorRef);

  chargementInitial = true;
  maPartie: PartieActive | null = null;
  membres: MembreJeu[] = [];

  // Rafraîchissement automatique : permet de détecter, sans recharger la
  // page, que l'adversaire a accepté une invitation, joué un coup, ou que
  // la partie est terminée (statut 'terminee', vu via le rafraîchissement
  // plutôt que via un évènement éphémère — voir explication détaillée
  // dans traiterFinDePartie ci-dessous).
  private intervalId: ReturnType<typeof setInterval> | null = null;

  // Minuteur SÉPARÉ, toutes les secondes — purement visuel (fait défiler
  // l'affichage du chrono), ne contacte le serveur QUE si un temps tombe
  // à zéro. Volontairement distinct du rafraîchissement toutes les 3s.
  private intervalChrono: ReturnType<typeof setInterval> | null = null;
  private signalementTempsEcouleEnvoye = false;

  // "Battement de cœur" — signale au serveur qu'on est toujours là,
  // toutes les 5 secondes pendant qu'une partie est en cours.
  private intervalSignal: ReturnType<typeof setInterval> | null = null;
  private signalementDeconnexionEnvoye = false;

  ngOnInit(): void {
    this.chargerMembres();
    this.chargerMaPartie(true);
    this.chargerClassement();

    this.intervalId = setInterval(() => {
      this.chargerMaPartie(false);
    }, 3000);

    this.intervalChrono = setInterval(() => {
      this.verifierChrono();
      this.verifierDeconnexionAdversaire();
    }, 1000);

    this.intervalSignal = setInterval(() => {
      this.envoyerSignal();
    }, 5000);
  }

  ngOnDestroy(): void {
    if (this.intervalId) {
      clearInterval(this.intervalId);
    }
    if (this.intervalChrono) {
      clearInterval(this.intervalChrono);
    }
    if (this.intervalSignal) {
      clearInterval(this.intervalSignal);
    }
  }

  private chargerMaPartie(premierChargement: boolean): void {
    this.jeuService.maPartie().subscribe({
      next: (data) => {
        this.maPartie = data;
        if (premierChargement) {
          this.chargementInitial = false;
        }
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.warn('Échec du rafraîchissement de la partie :', err);
      }
    });
  }

  private chargerMembres(): void {
    this.jeuService.membresDisponibles().subscribe({
      next: (data) => {
        this.membres = data;
        this.cdr.detectChanges();
      }
    });
  }

  // ---------- Classement ----------

  classementListe: ClassementEntry[] = [];

  private chargerClassement(): void {
    this.jeuService.classement().subscribe({
      next: (data) => {
        this.classementListe = data;
        this.cdr.detectChanges();
      }
    });
  }

  // "trait" (côté backend) vaut 'blanc' ou 'noir' — on compare avec sa
  // propre couleur pour savoir si c'est à soi de jouer.
  estMonTour(): boolean {
    if (!this.maPartie || !this.maPartie.trait) {
      return false;
    }
    return this.maPartie.trait === (this.maPartie.je_suis_blanc ? 'blanc' : 'noir');
  }

  // ---------- Chronomètre ----------

  // Le temps stocké en base n'est mis à jour qu'À CHAQUE COUP joué —
  // entre-temps, il faut recalculer le temps RÉEL en déduisant les
  // secondes écoulées depuis le dernier coup, mais UNIQUEMENT pour le
  // camp qui a actuellement le trait (l'autre chrono, lui, ne tourne pas).
  private calculerTempsRestant(couleur: 'blanc' | 'noir'): number {
    if (!this.maPartie) {
      return 600;
    }

    const stocke = couleur === 'blanc' ? this.maPartie.temps_restant_blanc : this.maPartie.temps_restant_noir;
    const valeurStockee = stocke ?? 600;

    if (this.maPartie.trait !== couleur || !this.maPartie.dernier_coup_le) {
      return valeurStockee;
    }

    const ecouleSecondes = (Date.now() - new Date(this.maPartie.dernier_coup_le).getTime()) / 1000;
    return Math.max(0, Math.round(valeurStockee - ecouleSecondes));
  }

  tempsAfficheBlanc(): string {
    return this.formaterTemps(this.calculerTempsRestant('blanc'));
  }

  tempsAfficheNoir(): string {
    return this.formaterTemps(this.calculerTempsRestant('noir'));
  }

  private formaterTemps(secondes: number): string {
    const minutes = Math.floor(secondes / 60);
    const reste = secondes % 60;
    return `${minutes}:${reste.toString().padStart(2, '0')}`;
  }

  private verifierChrono(): void {
    if (!this.maPartie || this.maPartie.statut !== 'en_cours' || !this.maPartie.trait) {
      return;
    }

    // Rafraîchit juste l'affichage du compte à rebours à l'écran.
    this.cdr.detectChanges();

    if (this.signalementTempsEcouleEnvoye) {
      return;
    }

    const tempsAuTrait = this.calculerTempsRestant(this.maPartie.trait as 'blanc' | 'noir');
    if (tempsAuTrait <= 0) {
      // N'importe lequel des deux joueurs peut signaler ça — pas
      // seulement celui dont le temps est écoulé (voir explication côté
      // backend : utile si c'est justement lui qui a fermé son navigateur).
      this.signalementTempsEcouleEnvoye = true;
      this.jeuService.tempsEcoule(this.maPartie.id).subscribe({
        next: () => {
          this.signalementTempsEcouleEnvoye = false;
          this.chargerMaPartie(false);
        },
        error: () => {
          this.signalementTempsEcouleEnvoye = false;
        },
      });
    }
  }

  // ---------- Détection de déconnexion ----------

  private envoyerSignal(): void {
    if (!this.maPartie || this.maPartie.statut !== 'en_cours') {
      return;
    }
    this.jeuService.signal(this.maPartie.id).subscribe();
  }

  private verifierDeconnexionAdversaire(): void {
    if (!this.maPartie || this.maPartie.statut !== 'en_cours' || this.signalementDeconnexionEnvoye) {
      return;
    }

    // Le signal de L'ADVERSAIRE (pas le mien) — s'il n'a plus donné signe
    // de vie depuis plus de 20 secondes, on le signale au serveur (qui
    // revérifiera avant de trancher).
    const signalAdversaire = this.maPartie.je_suis_blanc
      ? this.maPartie.dernier_signal_noir
      : this.maPartie.dernier_signal_blanc;

    if (!signalAdversaire) {
      return;
    }

    const secondesDepuisSignal = (Date.now() - new Date(signalAdversaire).getTime()) / 1000;
    if (secondesDepuisSignal < 20) {
      return;
    }

    this.signalementDeconnexionEnvoye = true;
    this.jeuService.declarerDeconnexion(this.maPartie.id).subscribe({
      next: () => {
        this.signalementDeconnexionEnvoye = false;
        this.chargerMaPartie(false);
      },
      error: () => {
        this.signalementDeconnexionEnvoye = false;
      },
    });
  }

  // ---------- Proposer une partie ----------

  modaleCreationOuverte = false; // conservé pour compat, non utilisé ici
  adversaireChoisi = '';
  classeeChoisi = true;
  messageErreurProposition = '';
  propositionEnCours = false;

  onAdversaireChange(valeur: string): void {
    this.adversaireChoisi = valeur;
  }

  proposerPartie(): void {
    if (this.propositionEnCours) {
      return;
    }

    this.messageErreurProposition = '';

    if (!this.adversaireChoisi) {
      this.messageErreurProposition = 'Choisissez un adversaire.';
      this.cdr.detectChanges();
      return;
    }

    this.propositionEnCours = true;

    this.jeuService.proposer(+this.adversaireChoisi, this.classeeChoisi).subscribe({
      next: () => {
        this.propositionEnCours = false;
        this.adversaireChoisi = '';
        this.chargerMaPartie(false);
      },
      error: (err) => {
        this.propositionEnCours = false;
        this.messageErreurProposition = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Accepter une invitation reçue ----------

  accepterEnCours = false;
  messageErreurAcceptation = '';

  accepterInvitation(): void {
    if (!this.maPartie || this.accepterEnCours) {
      return;
    }

    this.accepterEnCours = true;
    this.messageErreurAcceptation = '';

    this.jeuService.accepter(this.maPartie.id).subscribe({
      next: () => {
        this.accepterEnCours = false;
        this.chargerMaPartie(false);
      },
      error: (err) => {
        this.accepterEnCours = false;
        this.messageErreurAcceptation = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Refuser / annuler une invitation ----------

  confirmationAnnulationEnCours = false;

  demanderConfirmationAnnulation(): void {
    this.confirmationAnnulationEnCours = true;
  }

  annulerConfirmationAnnulation(): void {
    this.confirmationAnnulationEnCours = false;
  }

  confirmerAnnulation(): void {
    if (!this.maPartie) {
      return;
    }

    this.jeuService.annulerInvitation(this.maPartie.id).subscribe({
      next: () => {
        this.confirmationAnnulationEnCours = false;
        this.chargerMaPartie(false);
      }
    });
  }

  // ---------- Jouer un coup ----------

  onCoupJoue(evenement: {
    fen: string;
    coup: string;
    finPartie: { resultat: 'blanc' | 'noir' | 'nul'; motif: string } | null
  }): void {
    if (!this.maPartie) {
      return;
    }

    this.jeuService.jouerCoup(this.maPartie.id, evenement.fen, evenement.coup).subscribe({
      next: () => {
        // On ne signale la fin de partie qu'UNE FOIS le coup confirmé
        // enregistré — sinon la partie pourrait passer "terminee" avant
        // que ce dernier coup n'ait été sauvegardé.
        if (evenement.finPartie) {
          this.jeuService.terminerPartie(this.maPartie!.id, evenement.finPartie.resultat).subscribe({
            next: () => this.chargerMaPartie(false),
            error: () => this.chargerMaPartie(false),
          });
        } else {
          this.chargerMaPartie(false);
        }
      },
      error: () => {
        this.chargerMaPartie(false);
      },
    });
  }

  // ---------- Fin de partie détectée en RECEVANT le coup adverse ----------

  onPartieTerminee(evenement: { resultat: 'blanc' | 'noir' | 'nul'; motif: string }): void {
    if (!this.maPartie) {
      return;
    }
    // Idempotent côté serveur — même si l'adversaire l'a déjà signalé,
    // cet appel ne fait rien de mal, il confirme juste le même état.
    this.jeuService.terminerPartie(this.maPartie.id, evenement.resultat).subscribe({
      next: () => this.chargerMaPartie(false),
      error: () => this.chargerMaPartie(false),
    });
  }

  // ---------- Affichage du résultat (piloté par l'état du serveur, pas
  // par l'évènement éphémère — donc visible même après un rechargement
  // de page, ou si on a raté l'instant précis de la détection) ----------

  texteResultat(): string {
    if (!this.maPartie || this.maPartie.resultat === null) {
      return '';
    }
    if (this.maPartie.resultat === 'nul') {
      return 'Match nul';
    }
    const jaiGagne = (this.maPartie.resultat === 'blanc' && this.maPartie.je_suis_blanc)
      || (this.maPartie.resultat === 'noir' && !this.maPartie.je_suis_blanc);
    return jaiGagne ? 'Victoire !' : 'Défaite';
  }

  fermerPartieTerminee(): void {
    if (!this.maPartie) {
      return;
    }
    this.jeuService.annulerInvitation(this.maPartie.id).subscribe({
      next: () => {
        this.maPartie = null;
        this.chargerClassement();
        this.cdr.detectChanges();
      },
      error: () => {
        this.maPartie = null;
        this.chargerClassement();
        this.cdr.detectChanges();
      },
    });
  }

  // ---------- Abandonner ----------

  confirmationAbandonEnCours = false;

  demanderConfirmationAbandon(): void {
    this.confirmationAbandonEnCours = true;
  }

  annulerConfirmationAbandon(): void {
    this.confirmationAbandonEnCours = false;
  }

  confirmerAbandon(): void {
    if (!this.maPartie) {
      return;
    }
    this.jeuService.abandonner(this.maPartie.id).subscribe({
      next: () => {
        this.confirmationAbandonEnCours = false;
        this.chargerMaPartie(false);
      }
    });
  }

  // ---------- Proposer / répondre à une nulle ----------

  propositionNulEnCours = false;

  proposerNul(): void {
    if (!this.maPartie || this.propositionNulEnCours) {
      return;
    }
    this.propositionNulEnCours = true;

    this.jeuService.proposerNul(this.maPartie.id).subscribe({
      next: () => {
        this.propositionNulEnCours = false;
        this.chargerMaPartie(false);
      },
      error: () => {
        this.propositionNulEnCours = false;
      },
    });
  }

  // "C'est MOI qui ai proposé" vs "l'adversaire me propose, à moi de répondre"
  jaiProposeNul(): boolean {
    if (!this.maPartie || !this.maPartie.nul_propose_par) {
      return false;
    }
    return this.maPartie.nul_propose_par === (this.maPartie.je_suis_blanc ? 'blanc' : 'noir');
  }

  repondreNul(accepter: boolean): void {
    if (!this.maPartie) {
      return;
    }
    this.jeuService.repondreNul(this.maPartie.id, accepter).subscribe({
      next: () => {
        this.chargerMaPartie(false);
      }
    });
  }
}
