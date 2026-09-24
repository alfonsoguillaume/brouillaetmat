import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

export interface MembreJeu {
  id: number;
  nom: string;
  prenom: string;
  en_ligne: boolean;
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
  fen: string | null;
  coups: string[] | null;
  trait: string | null;
  resultat: string | null;
  temps_restant_blanc: number | null;
  temps_restant_noir: number | null;
  dernier_coup_le: string | null;
  dernier_signal_blanc: string | null;
  dernier_signal_noir: string | null;
  nul_propose_par: string | null;
}

export interface ClassementEntry {
  nom: string;
  prenom: string;
  elo: number;
  en_ligne: boolean;
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

  jouerCoup(id: number, fen: string, coup: string) {
    return this.http.post<PartieActive>(`${this.apiUrl}/${id}/coup`, {fen, coup});
  }

  terminerPartie(id: number, resultat: 'blanc' | 'noir' | 'nul') {
    return this.http.post<{ message: string; resultat: string }>(`${this.apiUrl}/${id}/terminer`, {resultat});
  }

  tempsEcoule(id: number) {
    return this.http.post<PartieActive>(`${this.apiUrl}/${id}/temps-ecoule`, {});
  }

  signal(id: number) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${id}/signal`, {});
  }

  declarerDeconnexion(id: number) {
    return this.http.post<PartieActive>(`${this.apiUrl}/${id}/deconnexion`, {});
  }

  classement() {
    return this.http.get<ClassementEntry[]>(`${this.apiUrl}/classement`);
  }

  abandonner(id: number) {
    return this.http.post<PartieActive>(`${this.apiUrl}/${id}/abandonner`, {});
  }

  proposerNul(id: number) {
    return this.http.post<PartieActive>(`${this.apiUrl}/${id}/proposer-nul`, {});
  }

  repondreNul(id: number, accepter: boolean) {
    return this.http.post<PartieActive>(`${this.apiUrl}/${id}/repondre-nul`, {accepter});
  }

  signalPresence() {
    return this.http.post<{ message: string }>(`${this.apiUrl}/signal-presence`, {});
  }
}
