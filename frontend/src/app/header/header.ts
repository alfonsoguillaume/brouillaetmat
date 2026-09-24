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

  // Propriété qui gère l'état d'ouverture du menu sur mobile
  isMenuOpen: boolean = false;

  // Badge "quelque chose vous attend" sur le lien "Jouer" — utile pour ne
  // pas manquer une invitation ou une proposition de nulle si on se
  // promène ailleurs sur le site (la page Jouer elle-même l'affiche déjà
  // directement, pas besoin du badge quand on y est déjà).
  aUneNotificationJeu = false;
  private surPageJouer = false;
  private intervalleNotifJeu: ReturnType<typeof setInterval> | null = null;

  constructor() {
    // Ferme automatiquement le menu à chaque navigation réussie, et
    // retient si on est sur la page Jouer (pour masquer le badge dessus).
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
    }, 5000);
    // Premier contrôle immédiat, pas besoin d'attendre 5 secondes après
    // le chargement de la page.
    this.verifierNotificationJeu();
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
            // Invitation reçue, en attente de ma réponse.
            (partie.statut === 'en_attente' && !partie.je_suis_blanc)
            // L'adversaire propose une nulle.
            || (partie.statut === 'en_cours' && partie.nul_propose_par !== null && partie.nul_propose_par !== maCouleur)
            // Mon invitation a été refusée — j'ai un message à fermer.
            || (partie.statut === 'refusee' && partie.je_suis_blanc);
        }
        this.cdr.detectChanges();
      },
      error: () => {
        // Pas grave si ça échoue une fois — le prochain essai, 5s plus
        // tard, retentera tout seul.
      }
    });
  }

  // Méthode appelée au clic sur le bouton burger
  toggleMenu(): void {
    this.isMenuOpen = !this.isMenuOpen;
  }

  // Méthode appelée au clic sur un lien du menu
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

  logout(): void {
    this.authService.logout();
  }
}
