import {Component} from '@angular/core';
import {RouterLink, RouterLinkActive} from '@angular/router';

@Component({
  imports: [RouterLink, RouterLinkActive],
  selector: 'app-header',
  styleUrl: './header.css',
  templateUrl: './header.html',
})
export class Header {
  // Propriété qui gère l'état d'ouverture du menu sur mobile
  isMenuOpen: boolean = false;

  // Méthode appelée au clic sur le bouton burger
  toggleMenu(): void {
    this.isMenuOpen = !this.isMenuOpen;
  }

  isLoggedIn(): boolean {
    return false;
  }

  isAdmin(): boolean {
    return false;
  }

  isGestionnaire(): boolean {
    return false;
  }

  logout(): void {
    console.log('déconnexion (pas encore branchée)');
  }
}
