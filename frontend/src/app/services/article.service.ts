import {inject, Injectable} from '@angular/core';
import {HttpClient} from '@angular/common/http';

export interface Article {
  id: number;
  titre: string;
  contenu: string;
  photo: string | null;
  date_creation: string;
  auteur: string;
  modificateur: string | null;
  date_modification: string | null;
}

@Injectable({providedIn: 'root'})
export class ArticleService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api/articles';

  liste() {
    return this.http.get<Article[]>(this.apiUrl);
  }

  creer(titre: string, contenu: string, photo: File | null) {
    const formData = new FormData();
    formData.append('titre', titre);
    formData.append('contenu', contenu);
    if (photo) {
      formData.append('photo', photo);
    }

    return this.http.post<{ message: string; id: number }>(this.apiUrl, formData);
  }

  modifier(id: number, titre: string, contenu: string, photo: File | null) {
    const formData = new FormData();
    formData.append('titre', titre);
    formData.append('contenu', contenu);
    if (photo) {
      formData.append('photo', photo);
    }

    // POST plutôt que PATCH : PHP ne sait pas nativement lire un corps
    // multipart/form-data envoyé en PATCH — c'est une limitation connue,
    // contournée en gardant POST pour cette route côté backend aussi.
    return this.http.post<{ message: string }>(`${this.apiUrl}/${id}`, formData);
  }

  supprimer(id: number) {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/${id}`);
  }
}
