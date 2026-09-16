import {Component, computed, ElementRef, inject, signal, ViewChild} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {AuthService} from '../services/auth.service';
import {Article, ArticleService} from '../services/article.service';

const BASE_URL_PHOTOS = 'http://localhost:8000/uploads/articles/';

@Component({
  selector: 'app-club-component',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './club-component.html',
  styleUrl: './club-component.css'
})
export class ClubComponent {
  authService = inject(AuthService);
  private articleService = inject(ArticleService);
  private fb = inject(FormBuilder);

  @ViewChild('topClub') topClubRef!: ElementRef;

  articleSelectionne = signal<Article | null>(null);

  // --- PAGINATION ---
  pageActuelle = signal<number>(1);
  articlesParPage = signal<number>(5);

  articles = signal<Article[]>([]);

  constructor() {
    this.chargerArticles();
  }

  chargerArticles(): void {
    this.articleService.liste().subscribe({
      next: (data) => this.articles.set(data)
    });
  }

  // Construit l'URL complète de la photo, ou null si l'article n'en a pas.
  urlPhoto(article: Article): string | null {
    return article.photo ? BASE_URL_PHOTOS + article.photo : null;
  }

  estGestionnaire(): boolean {
    return this.authService.isGestionnaire();
  }

  // Nombre total de pages
  totalPages = computed(() => {
    return Math.ceil(this.articles().length / this.articlesParPage()) || 1;
  });

  // Articles filtrés pour la page en cours
  articlesAffiches = computed(() => {
    const indexDebut = (this.pageActuelle() - 1) * this.articlesParPage();
    const indexFin = indexDebut + this.articlesParPage();
    return this.articles().slice(indexDebut, indexFin);
  });

  // Navigation entre les pages
  changerPage(nouvellePage: number): void {
    if (nouvellePage >= 1 && nouvellePage <= this.totalPages()) {
      this.pageActuelle.set(nouvellePage);

      if (this.topClubRef) {
        this.topClubRef.nativeElement.scrollIntoView({behavior: 'smooth'});
      }
    }
  }

  ouvrirModale(article: Article): void {
    this.articleSelectionne.set(article);
    this.modeEdition.set(false);
  }

  fermerModale(): void {
    this.articleSelectionne.set(null);
    this.modeEdition.set(false);
  }

  // ---------- Édition d'un article ----------

  modeEdition = signal<boolean>(false);
  photoEditionSelectionnee: File | null = null;
  messagesErreursEdition: string[] = [];
  publicationEnCours = false;

  editionForm: FormGroup = this.fb.group({
    titre: ['', Validators.required],
    contenu: ['', Validators.required],
  });

  passerEnModeEdition(): void {
    const article = this.articleSelectionne();
    if (!article) {
      return;
    }

    this.editionForm.setValue({
      titre: article.titre,
      contenu: article.contenu,
    });
    this.photoEditionSelectionnee = null;
    this.messagesErreursEdition = [];
    this.modeEdition.set(true);
  }

  annulerEdition(): void {
    this.modeEdition.set(false);
  }

  onPhotoEditionSelectionnee(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.photoEditionSelectionnee = input.files && input.files.length > 0 ? input.files[0] : null;
  }

  enregistrerModification(): void {
    const article = this.articleSelectionne();
    if (!article || this.publicationEnCours) {
      return;
    }

    this.messagesErreursEdition = [];

    if (!this.editionForm.valid) {
      const messages: string[] = [];
      if (this.editionForm.get('titre')?.errors?.['required']) {
        messages.push('Remplissez le titre.');
      }
      if (this.editionForm.get('contenu')?.errors?.['required']) {
        messages.push('Remplissez le contenu.');
      }
      this.messagesErreursEdition = messages;
      return;
    }

    const {titre, contenu} = this.editionForm.value;
    this.publicationEnCours = true;

    this.articleService.modifier(article.id, titre, contenu, this.photoEditionSelectionnee).subscribe({
      next: () => {
        this.publicationEnCours = false;
        this.fermerModale();
        this.chargerArticles();
      },
      error: (err) => {
        this.publicationEnCours = false;
        this.messagesErreursEdition = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
      },
    });
  }

  // ---------- Suppression d'un article ----------

  confirmationSuppressionEnCours = signal<boolean>(false);

  demanderConfirmationSuppression(): void {
    this.confirmationSuppressionEnCours.set(true);
  }

  annulerConfirmationSuppression(): void {
    this.confirmationSuppressionEnCours.set(false);
  }

  confirmerSuppression(): void {
    const article = this.articleSelectionne();
    if (!article) {
      return;
    }

    this.articleService.supprimer(article.id).subscribe({
      next: () => {
        this.confirmationSuppressionEnCours.set(false);
        this.fermerModale();
        this.chargerArticles();
      }
    });
  }
}
