import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';

@Component({
  imports: [RouterLink, RouterLinkActive],
  selector: 'app-header',
  styleUrl: './header.css',
  templateUrl: './header.html',
})
export class Header {

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
