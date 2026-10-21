import {API_URL} from '../api.config';
import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

@Injectable({providedIn: 'root'})
export class MotDePasseOublieService {
  private http = inject(HttpClient);
  private apiUrl = `${API_URL}/api`;

  demanderReinitialisation(email: string) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/mot-de-passe-oublie`, {email});
  }

  reinitialiserMotDePasse(token: string, nouveauMotDePasse: string) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/reinitialiser-mot-de-passe/${token}`, {
      nouveau_mot_de_passe: nouveauMotDePasse,
    });
  }
}
