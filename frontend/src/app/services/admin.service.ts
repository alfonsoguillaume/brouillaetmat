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
}
