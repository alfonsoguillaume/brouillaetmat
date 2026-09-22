import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

export interface InscriptionEnAttente {
  id: number;
  nom: string;
  prenom: string;
  pseudo: string;
  email: string;
  date_naissance: string;
  telephone: string | null;
  email_tuteur: string | null;
}

export interface Membre {
  id: number;
  nom: string;
  prenom: string;
  pseudo: string;
  email: string;
  telephone: string | null;
  email_tuteur: string | null;
  role: string;
  statut_inscription: string;
  commentaire: string | null;
}

export interface ModificationMembre {
  nom: string;
  prenom: string;
  pseudo: string;
  email: string;
  telephone: string | null;
  email_tuteur: string | null;
  commentaire: string | null;
}

@Injectable({providedIn: 'root'})
export class AdminService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api/admin';

  listeInscriptions() {
    return this.http.get<InscriptionEnAttente[]>(`${this.apiUrl}/inscriptions`);
  }

  valider(id: number) {
    return this.http.patch(`${this.apiUrl}/inscriptions/${id}/valider`, {});
  }

  refuser(id: number) {
    return this.http.delete(`${this.apiUrl}/inscriptions/${id}/refuser`);
  }

  listeMembres() {
    return this.http.get<Membre[]>(`${this.apiUrl}/membres`);
  }

  modifierMembre(id: number, payload: ModificationMembre) {
    return this.http.patch<{ message: string }>(`${this.apiUrl}/membres/${id}`, payload);
  }

  changerRole(id: number, role: string) {
    return this.http.patch<{ message: string }>(`${this.apiUrl}/membres/${id}/role`, {role});
  }

  bloquerMembre(id: number) {
    return this.http.patch<{ message: string }>(`${this.apiUrl}/membres/${id}/bloquer`, {});
  }

  debloquerMembre(id: number) {
    return this.http.patch<{ message: string }>(`${this.apiUrl}/membres/${id}/debloquer`, {});
  }

  supprimerMembre(id: number) {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/membres/${id}`);
  }
}
