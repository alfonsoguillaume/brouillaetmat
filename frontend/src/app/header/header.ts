import {Component, inject} from '@angular/core';
import {RouterLink, RouterLinkActive} from '@angular/router';
import {AuthService} from '../services/auth.service';

@Component({
  imports: [RouterLink, RouterLinkActive],
  selector: 'app-header',
  styleUrl: './header.css',
  templateUrl: './header.html',
})
export class Header {
  private authService = inject(AuthService);

  // Propriété qui gère l'état d'ouverture du menu sur mobile
  isMenuOpen: boolean = false;

  // Méthode appelée au clic sur le bouton burger
  toggleMenu(): void {
    this.isMenuOpen = !this.isMenuOpen;
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
