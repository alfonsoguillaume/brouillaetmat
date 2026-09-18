import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

export interface TournoiListe {
  id: number;
  nom: string;
  date: string;
  statut: string;
  nombre_participants: number;
}

export interface ParticipantTournoi {
  utilisateur_id: number;
  nom: string;
  prenom: string;
  resultat: string | null;
}

export interface TournoiDetail {
  id: number;
  nom: string;
  date: string;
  statut: string;
  participants: ParticipantTournoi[];
}

export interface MembreLeger {
  id: number;
  nom: string;
  prenom: string;
}

export interface TournoiArchive {
  id: number;
  nom: string;
  date: string;
  statut: string;
  motif_annulation: string | null;
  date_annulation: string | null;
}

@Injectable({providedIn: 'root'})
export class TournoisService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api/tournois';

  liste() {
    return this.http.get<TournoiListe[]>(this.apiUrl);
  }

  detail(id: number) {
    return this.http.get<TournoiDetail>(`${this.apiUrl}/${id}`);
  }

  membresDisponibles() {
    return this.http.get<MembreLeger[]>(`${this.apiUrl}/membres-disponibles`);
  }

  creer(nom: string, date: string) {
    return this.http.post<{ message: string; id: number }>(this.apiUrl, {nom, date});
  }

  modifier(id: number, nom: string, date: string) {
    return this.http.patch<{ message: string }>(`${this.apiUrl}/${id}`, {nom, date});
  }

  annuler(id: number, motif: string) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${id}/annuler`, {motif});
  }

  listeAnnules() {
    return this.http.get<TournoiArchive[]>(`${this.apiUrl}/annules`);
  }

  inscrireParticipant(tournoiId: number, utilisateurId: number) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${tournoiId}/participants`, {
      utilisateur_id: utilisateurId,
    });
  }

  desinscrireParticipant(tournoiId: number, utilisateurId: number) {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/${tournoiId}/participants/${utilisateurId}`);
  }

  lancer(id: number) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${id}/lancer`, {});
  }
}
