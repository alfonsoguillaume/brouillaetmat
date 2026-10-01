import {inject, Injectable, signal} from '@angular/core';
import {HttpClient} from '@angular/common/http';
import {tap} from 'rxjs/operators';

interface LoginResponse {
  token: string;
}

interface MeResponse {
  email: string;
  pseudo: string;
  roles: string[];
}

@Injectable({providedIn: 'root'})
export class AuthService {
  private http = inject(HttpClient);

  private apiUrl = 'http://localhost:8000/api';

  private loggedIn = signal<boolean>(!!localStorage.getItem('token'));
  private roles = signal<string[]>([]);
  private pseudo = signal<string | null>(null);
  readonly pseudoSignal = this.pseudo.asReadonly();

  // vrai dès qu'on connait le statut connecté + rôle, le guard attend ce signal avant d'autoriser une page
  private profilCharge = signal<boolean>(!this.loggedIn());
  readonly profilChargeSignal = this.profilCharge.asReadonly();

  constructor() {
    // si token déjà present (page rafraîchie, nouvel onglet), recharge le profil tout de suite
    if (this.loggedIn()) {
      this.chargerProfil();
    }
  }

  isLoggedIn(): boolean {
    return this.loggedIn();
  }

  isAdmin(): boolean {
    return this.roles().includes('ROLE_ADMIN');
  }

  isGestionnaire(): boolean {
    return this.roles().includes('ROLE_GESTIONNAIRE') || this.isAdmin();
  }

  getPseudo(): string | null {
    return this.pseudo();
  }

  login(email: string, password: string) {
    return this.http.post<LoginResponse>(`${this.apiUrl}/login`, {email, password}).pipe(
      tap((response) => {
        localStorage.setItem('token', response.token);
        this.loggedIn.set(true);
        this.chargerProfil();
      })
    );
  }

  chargerProfil(): void {
    this.http.get<MeResponse>(`${this.apiUrl}/me`).subscribe({
      next: (me) => {
        this.roles.set(me.roles);
        this.pseudo.set(me.pseudo);
        this.profilCharge.set(true);
      },
      error: () => {
        this.logout();
        this.profilCharge.set(true);
      }
    });
  }

  logout(): void {
    localStorage.removeItem('token');
    this.loggedIn.set(false);
    this.roles.set([]);
    this.pseudo.set(null);
  }
}
