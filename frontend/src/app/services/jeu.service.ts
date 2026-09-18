import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

export interface MembreJeu {
  id: number;
  nom: string;
  prenom: string;
}

export interface JoueurInfo {
  id: number;
  nom: string;
  prenom: string;
}

export interface PartieActive {
  id: number;
  statut: string;
  classee: boolean;
  joueur_blanc: JoueurInfo;
  joueur_noir: JoueurInfo;
  je_suis_blanc: boolean;
}

@Injectable({providedIn: 'root'})
export class JeuService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api/jeu';

  membresDisponibles() {
    return this.http.get<MembreJeu[]>(`${this.apiUrl}/membres`);
  }

  maPartie() {
    return this.http.get<PartieActive | null>(`${this.apiUrl}/ma-partie`);
  }

  proposer(adversaireId: number, classee: boolean) {
    return this.http.post<{ message: string; id: number }>(`${this.apiUrl}/proposer`, {
      adversaire_id: adversaireId,
      classee,
    });
  }

  accepter(id: number) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${id}/accepter`, {});
  }

  annulerInvitation(id: number) {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/${id}`);
  }
}
