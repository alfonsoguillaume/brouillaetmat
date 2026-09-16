import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

export interface Ouvrage {
  id: number;
  titre: string;
  auteur: string;
  disponible: boolean;
}

export interface MembreLeger {
  id: number;
  nom: string;
  prenom: string;
}

export interface EmpruntEnCours {
  id: number;
  ouvrage_titre: string;
  membre: string;
  date_emprunt: string;
}

export interface ArchiveEmpruntLigne {
  id: number;
  ouvrage_titre: string;
  membre: string;
  date_emprunt: string;
  date_retour: string;
}

@Injectable({providedIn: 'root'})
export class BibliothequeService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api/bibliotheque';

  // --- Ouvrages ---
  listeOuvrages() {
    return this.http.get<Ouvrage[]>(`${this.apiUrl}/ouvrages`);
  }

  ajouterOuvrage(titre: string, auteur: string) {
    return this.http.post<{ message: string; id: number }>(`${this.apiUrl}/ouvrages`, {titre, auteur});
  }

  modifierOuvrage(id: number, titre: string, auteur: string) {
    return this.http.patch<{ message: string }>(`${this.apiUrl}/ouvrages/${id}`, {titre, auteur});
  }

  supprimerOuvrage(id: number) {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/ouvrages/${id}`);
  }

  // --- Membres ---
  listeMembres() {
    return this.http.get<MembreLeger[]>(`${this.apiUrl}/membres`);
  }

  // --- Emprunts ---
  listeEmprunts() {
    return this.http.get<EmpruntEnCours[]>(`${this.apiUrl}/emprunts`);
  }

  creerEmprunt(ouvrageId: number, utilisateurId: number) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/emprunts`, {
      ouvrage_id: ouvrageId,
      utilisateur_id: utilisateurId,
    });
  }

  marquerRetour(id: number) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/emprunts/${id}/retour`, {});
  }

  // --- Archives ---
  listeArchives() {
    return this.http.get<ArchiveEmpruntLigne[]>(`${this.apiUrl}/archives`);
  }
}
