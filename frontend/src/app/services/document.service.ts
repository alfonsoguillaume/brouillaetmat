import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

// DocumentAdmin pour pas confondre avec le type Document du navigateur
export interface DocumentAdmin {
  id: number;
  nom: string;
  fichier: string;
  date_ajout: string;
  ajoute_par: string;
}

export interface Dossier {
  id: number;
  nom: string;
  nombre_documents: number;
}

@Injectable({providedIn: 'root'})
export class DocumentService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api/documents';

  liste(dossierId: number | null) {
    const params = dossierId ? `?dossier_id=${dossierId}` : '';
    return this.http.get<DocumentAdmin[]>(`${this.apiUrl}${params}`);
  }

  listeDossiers() {
    return this.http.get<Dossier[]>(`${this.apiUrl}/dossiers`);
  }

  creerDossier(nom: string) {
    return this.http.post<{ message: string; id: number }>(`${this.apiUrl}/dossiers`, {nom});
  }

  supprimerDossier(id: number) {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/dossiers/${id}`);
  }

  ajouter(nom: string, fichier: File, dossierId: number | null) {
    const formData = new FormData();
    formData.append('nom', nom);
    formData.append('fichier', fichier);
    if (dossierId) {
      formData.append('dossier_id', dossierId.toString());
    }

    return this.http.post<{ message: string; id: number }>(this.apiUrl, formData);
  }

  supprimer(id: number) {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/${id}`);
  }

  // blob car fichier binaire, pas du JSON
  telecharger(id: number) {
    return this.http.get(`${this.apiUrl}/${id}/telecharger`, {responseType: 'blob'});
  }
}
