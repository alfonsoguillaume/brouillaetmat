import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

export interface Profil {
  nom: string;
  prenom: string;
  pseudo: string;
  email: string;
  telephone: string | null;
  date_naissance: string;
  email_tuteur: string | null;
  role: string;
}

export interface ModificationProfil {
  nom: string;
  prenom: string;
  pseudo: string;
  telephone: string | null;
  email_tuteur: string | null;
}

export interface ChangementMotDePasse {
  mot_de_passe_actuel: string;
  nouveau_mot_de_passe: string;
}

@Injectable({providedIn: 'root'})
export class ProfilService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api/profil';

  getProfil() {
    return this.http.get<Profil>(this.apiUrl);
  }

  modifierProfil(payload: ModificationProfil) {
    return this.http.patch<{ message: string }>(this.apiUrl, payload);
  }

  changerMotDePasse(payload: ChangementMotDePasse) {
    return this.http.patch<{ message: string }>(`${this.apiUrl}/mot-de-passe`, payload);
  }
}
