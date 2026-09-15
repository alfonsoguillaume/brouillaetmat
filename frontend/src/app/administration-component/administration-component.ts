import {ChangeDetectorRef, Component, inject, OnInit} from '@angular/core';
import {DatePipe} from '@angular/common';
import {AdminService, InscriptionEnAttente} from '../services/admin.service';
import {AuthService} from '../services/auth.service';

@Component({
  imports: [DatePipe],
  selector: 'app-administration-component',
  styleUrl: './administration-component.css',
  templateUrl: './administration-component.html',
})
export class AdministrationComponent implements OnInit {
  private adminService = inject(AdminService);
  private authService = inject(AuthService);
  private cdr = inject(ChangeDetectorRef);

  inscriptions: InscriptionEnAttente[] = [];
  modaleOuverte = false;
  confirmationEnCours: { id: number; action: 'valider' | 'refuser' } | null = null;

  estAdmin(): boolean {
    return this.authService.isAdmin();
  }

  ngOnInit(): void {
    // Seul un admin a le droit d'appeler ces routes côté Symfony (ROLE_ADMIN) —
    // inutile de charger la liste si ce n'est pas le cas, ça éviterait juste
    // une erreur 403 sans intérêt pour un gestionnaire.
    if (this.estAdmin()) {
      this.chargerInscriptions();
    }
  }

  chargerInscriptions(): void {
    this.adminService.listeInscriptions().subscribe({
      next: (data) => {
        this.inscriptions = data;
        this.cdr.detectChanges();
      }
    });
  }

  ouvrirModale(): void {
    this.modaleOuverte = true;
  }

  fermerModale(): void {
    this.modaleOuverte = false;
    this.confirmationEnCours = null;
  }

  demanderConfirmation(id: number, action: 'valider' | 'refuser'): void {
    this.confirmationEnCours = {id, action};
  }

  annulerConfirmation(): void {
    this.confirmationEnCours = null;
  }

  confirmer(): void {
    if (!this.confirmationEnCours) {
      return;
    }

    const {id, action} = this.confirmationEnCours;
    const requete = action === 'valider'
      ? this.adminService.valider(id)
      : this.adminService.refuser(id);

    requete.subscribe({
      next: () => {
        this.inscriptions = this.inscriptions.filter(inscription => inscription.id !== id);
        this.confirmationEnCours = null;
        this.cdr.detectChanges();
      }
    });
  }
}
