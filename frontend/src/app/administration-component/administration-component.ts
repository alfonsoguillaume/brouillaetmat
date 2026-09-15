import {ChangeDetectorRef, Component, inject, OnInit} from '@angular/core';
import {DatePipe} from '@angular/common';
import {AdminService, InscriptionEnAttente} from '../services/admin.service';

@Component({
  imports: [DatePipe],
  selector: 'app-administration-component',
  styleUrl: './administration-component.css',
  templateUrl: './administration-component.html',
})
export class AdministrationComponent implements OnInit {
  private adminService = inject(AdminService);
  private cdr = inject(ChangeDetectorRef);

  inscriptions: InscriptionEnAttente[] = [];
  modaleOuverte = false;
  confirmationEnCours: { id: number; action: 'valider' | 'refuser' } | null = null;

  ngOnInit(): void {
    this.chargerInscriptions();
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
