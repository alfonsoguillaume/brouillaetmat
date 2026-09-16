import {inject, Injectable, signal} from '@angular/core';
import {HttpClient} from '@angular/common/http';
import {tap} from 'rxjs/operators';

interface LoginResponse {
  token: string;
}

interface MeResponse {
  email: string;
  roles: string[];
}

@Injectable({providedIn: 'root'})
export class AuthService {
  private http = inject(HttpClient);

  private apiUrl = 'http://localhost:8000/api';

  private loggedIn = signal<boolean>(!!localStorage.getItem('token'));
  private roles = signal<string[]>([]);

  // "Vrai" dès qu'on sait avec certitude si l'utilisateur est connecté ou
  // non ET quel est son rôle — soit immédiatement (pas de token = rien à
  // charger), soit après la réponse de /api/me. Le garde de route (guard)
  // attendra ce signal avant de décider d'autoriser ou non une page, pour
  // ne jamais juger "trop tôt", avant que le rôle soit vraiment connu.
  private profilCharge = signal<boolean>(!this.loggedIn());
  readonly profilChargeSignal = this.profilCharge.asReadonly();

  constructor() {
    // Si un token existe déjà (page rafraîchie, ou nouvel onglet), on
    // récupère le profil immédiatement — sinon "roles" resterait vide
    // jusqu'au prochain login(), et isAdmin()/isGestionnaire() renverraient
    // toujours false même pour un admin déjà connecté.
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
  }
}
