import {Component, inject} from '@angular/core';
import {NavigationEnd, Router, RouterLink, RouterLinkActive} from '@angular/router';
import {filter} from 'rxjs/operators';
import {AuthService} from '../services/auth.service';

@Component({
  imports: [RouterLink, RouterLinkActive],
  selector: 'app-header',
  styleUrl: './header.css',
  templateUrl: './header.html',
})
export class Header {
  private authService = inject(AuthService);
  private router = inject(Router);

  // Propriété qui gère l'état d'ouverture du menu sur mobile
  isMenuOpen: boolean = false;

  constructor() {
    // Ferme automatiquement le menu à chaque navigation réussie
    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe(() => {
      this.isMenuOpen = false;
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
