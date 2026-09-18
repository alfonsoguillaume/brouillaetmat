import {ChangeDetectorRef, Component, inject, OnDestroy, OnInit} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {JeuService, MembreJeu, PartieActive} from '../services/jeu.service';

@Component({
  selector: 'app-jouer-component',
  standalone: true,
  imports: [FormsModule],
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
  // page, que l'adversaire a accepté une invitation qu'on a envoyée (ou
  // qu'il en a envoyé une nouvelle) — prépare aussi le terrain pour
  // l'étape 2 (synchronisation des coups pendant la partie).
  private intervalId: ReturnType<typeof setInterval> | null = null;

  ngOnInit(): void {
    this.chargerMembres();
    this.chargerMaPartie(true);

    this.intervalId = setInterval(() => {
      this.chargerMaPartie(false);
    }, 3000);
  }

  ngOnDestroy(): void {
    if (this.intervalId) {
      clearInterval(this.intervalId);
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

  // ---------- Proposer une partie ----------

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
}
