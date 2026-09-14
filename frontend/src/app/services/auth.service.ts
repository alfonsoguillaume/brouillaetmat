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
      next: (me) => this.roles.set(me.roles),
      error: () => this.logout()
    });
  }

  logout(): void {
    localStorage.removeItem('token');
    this.loggedIn.set(false);
    this.roles.set([]);
  }
}
