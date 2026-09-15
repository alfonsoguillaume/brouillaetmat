import {Component, computed, ElementRef, inject, signal, ViewChild} from '@angular/core';
import {CommonModule} from '@angular/common';
import {AuthService} from '../services/auth.service';

export interface Article {
  id: number;
  titre: string;
  contenu: string;
  imageUrl: string;
}

@Component({
  selector: 'app-club-component',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './club-component.html',
  styleUrl: './club-component.css'
})
export class ClubComponent {
  authService = inject(AuthService);

  @ViewChild('topClub') topClubRef!: ElementRef;

  articleSelectionne = signal<Article | null>(null);

  // --- PAGINATION ---
  pageActuelle = signal<number>(1);
  articlesParPage = signal<number>(5);

  // Jeu de données de test (étendu pour tester la pagination)
  articles = signal<Article[]>([
    {
      id: 1,
      titre: 'Reprise des entraînements',
      contenu: 'Voici le détail du calendrier pour la nouvelle saison...',
      imageUrl: 'https://picsum.photos/400/250?random=1'
    },
    {
      id: 2,
      titre: 'Résultats du dernier tournoi',
      contenu: 'Félicitations à tous les participants pour leurs performances...',
      imageUrl: 'https://picsum.photos/400/250?random=2'
    },
    {
      id: 3,
      titre: 'Stage de perfectionnement',
      contenu: 'Inscriptions ouvertes pour le stage de perfectionnement de la Toussaint...',
      imageUrl: 'https://picsum.photos/400/250?random=3'
    },
    {
      id: 4,
      titre: 'Assemblée Générale',
      contenu: 'Ordre du jour de la prochaine Assemblée Générale du club...',
      imageUrl: 'https://picsum.photos/400/250?random=4'
    },
    {
      id: 5,
      titre: 'Journée bénévolat',
      contenu: 'Appel aux bénévoles pour l’entretien du matériel et des locaux...',
      imageUrl: 'https://picsum.photos/400/250?random=5'
    },
    {
      id: 6,
      titre: 'Présentation des maillots',
      contenu: 'Découvrez les nouvelles tenues officielles pour cette saison...',
      imageUrl: 'https://picsum.photos/400/250?random=6'
    },
    {
      id: 7,
      titre: 'Repas de fin d’année',
      contenu: 'Inscriptions pour la grande soirée conviviale du club...',
      imageUrl: 'https://picsum.photos/400/250?random=7'
    },
    {
      id: 8,
      titre: 'Podium au championnat',
      contenu: 'Excellents résultats de nos équipes ce week-end...',
      imageUrl: 'https://picsum.photos/400/250?random=8'
    }
  ]);

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
  }

  fermerModale(): void {
    this.articleSelectionne.set(null);
  }
}
