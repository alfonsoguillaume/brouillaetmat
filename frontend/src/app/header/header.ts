import {ChangeDetectorRef, Component, inject, OnDestroy} from '@angular/core';
import {NavigationEnd, Router, RouterLink, RouterLinkActive} from '@angular/router';
import {filter} from 'rxjs/operators';
import {AuthService} from '../services/auth.service';
import {JeuService} from '../services/jeu.service';

@Component({
  imports: [RouterLink, RouterLinkActive],
  selector: 'app-header',
  styleUrl: './header.css',
  templateUrl: './header.html',
})
export class Header implements OnDestroy {
  private authService = inject(AuthService);
  private jeuService = inject(JeuService);
  private router = inject(Router);
  private cdr = inject(ChangeDetectorRef);

  // état d'ouverture du menu mobile
  isMenuOpen: boolean = false;

  // badge sur le lien Jouer, pour une invitation/nulle en attente ailleurs sur le site
  aUneNotificationJeu = false;
  private surPageJouer = false;
  private intervalleNotifJeu: ReturnType<typeof setInterval> | null = null;

  constructor() {
    // ferme le menu à chaque navigation, retient si on est sur la page Jouer
    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event) => {
      this.isMenuOpen = false;
      this.surPageJouer = (event as NavigationEnd).urlAfterRedirects.startsWith('/jouer');
      if (this.surPageJouer) {
        this.aUneNotificationJeu = false;
      }
    });

    this.intervalleNotifJeu = setInterval(() => {
      this.verifierNotificationJeu();
      this.envoyerSignalPresence();
    }, 5000);
    // premier contrôle immédiat, pas besoin d'attendre 5s
    this.verifierNotificationJeu();
    this.envoyerSignalPresence();
  }

  ngOnDestroy(): void {
    if (this.intervalleNotifJeu) {
      clearInterval(this.intervalleNotifJeu);
    }
  }

  private verifierNotificationJeu(): void {
    if (!this.authService.isLoggedIn() || this.surPageJouer) {
      return;
    }

    this.jeuService.maPartie().subscribe({
      next: (partie) => {
        if (!partie) {
          this.aUneNotificationJeu = false;
        } else {
          const maCouleur = partie.je_suis_blanc ? 'blanc' : 'noir';
          this.aUneNotificationJeu =
            // invitation reçue, en attente de ma réponse
            (partie.statut === 'en_attente' && !partie.je_suis_blanc)
            // adversaire propose une nulle
            || (partie.statut === 'en_cours' && partie.nul_propose_par !== null && partie.nul_propose_par !== maCouleur)
            // mon invitation refusée, message à fermer
            || (partie.statut === 'refusee' && partie.je_suis_blanc);
        }
        this.cdr.detectChanges();
      },
      error: () => {
        // pas grave si ça échoue, le prochain essai retentera dans 5s
      }
    });
  }

  private envoyerSignalPresence(): void {
    if (!this.authService.isLoggedIn()) {
      return;
    }
    // pas besoin de traiter la réponse, juste à jour la dernière activité côté serveur
    this.jeuService.signalPresence().subscribe({
      error: () => {
      }
    });
  }

  // clic sur le bouton burger
  toggleMenu(): void {
    this.isMenuOpen = !this.isMenuOpen;
  }

  // clic sur un lien du menu
  closeMenu(): void {
    this.isMenuOpen = false;
  }

  isLoggedIn(): boolean {
    return this.authService.isLoggedIn();
  }

  isAdmin(): boolean {
    return this.authService.isAdmin();
  }

  isGestionnaire(): boolean {
    return this.authService.isGestionnaire();
  }

  pseudo(): string | null {
    return this.authService.getPseudo();
  }

  logout(): void {
    this.authService.logout();
  }
}
