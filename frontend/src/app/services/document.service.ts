import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

// Nommé "DocumentAdmin" plutôt que "Document" pour ne pas entrer en conflit
// avec le type natif "Document" du navigateur (représente la page HTML elle-même).
export interface DocumentAdmin {
  id: number;
  nom: string;
  fichier: string;
  date_ajout: string;
  ajoute_par: string;
}

@Injectable({providedIn: 'root'})
export class DocumentService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api/documents';

  liste() {
    return this.http.get<DocumentAdmin[]>(this.apiUrl);
  }

  ajouter(nom: string, fichier: File) {
    const formData = new FormData();
    formData.append('nom', nom);
    formData.append('fichier', fichier);

    return this.http.post<{ message: string; id: number }>(this.apiUrl, formData);
  }

  supprimer(id: number) {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/${id}`);
  }

  // responseType: 'blob' : on attend des données binaires (le fichier
  // lui-même), pas du JSON — Angular sait alors les traiter comme un
  // fichier plutôt que d'essayer de les interpréter comme du texte.
  telecharger(id: number) {
    return this.http.get(`${this.apiUrl}/${id}/telecharger`, {responseType: 'blob'});
  }
}
