import {ChangeDetectorRef, Component, inject} from '@angular/core';
import {DatePipe} from '@angular/common';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {DocumentAdmin, DocumentService} from '../services/document.service';

@Component({
  selector: 'app-document-component',
  standalone: true,
  imports: [DatePipe, ReactiveFormsModule],
  templateUrl: './document-component.html',
  styleUrl: './document-component.css',
})
export class DocumentComponent {
  private documentService = inject(DocumentService);
  private fb = inject(FormBuilder);
  private cdr = inject(ChangeDetectorRef);

  documents: DocumentAdmin[] = [];

  modaleAjoutOuverte = false;
  fichierSelectionne: File | null = null;
  messagesErreursAjout: string[] = [];
  ajoutEnCours = false;

  ajoutForm: FormGroup = this.fb.group({
    nom: ['', Validators.required],
  });

  confirmationSuppressionEnCours: DocumentAdmin | null = null;

  constructor() {
    // Route déjà protégée par adminGuard (voir app.routes.ts) — pas besoin
    // de revérifier le rôle ici, on peut charger directement.
    this.chargerDocuments();
  }

  chargerDocuments(): void {
    this.documentService.liste().subscribe({
      next: (data) => {
        this.documents = data;
        this.cdr.detectChanges();
      }
    });
  }

  telechargerDocument(doc: DocumentAdmin): void {
    this.documentService.telecharger(doc.id).subscribe({
      next: (blob) => {
        // On crée une URL temporaire, propre au navigateur, pointant vers
        // les données binaires reçues — puis on simule un clic sur un lien
        // invisible pour déclencher le téléchargement, avant de nettoyer.
        const url = window.URL.createObjectURL(blob);
        const lien = window.document.createElement('a');
        lien.href = url;
        lien.download = doc.nom;
        lien.click();
        window.URL.revokeObjectURL(url);
      }
    });
  }

  ouvrirModaleAjout(): void {
    this.modaleAjoutOuverte = true;
    this.fichierSelectionne = null;
    this.messagesErreursAjout = [];
    this.ajoutForm.reset();
  }

  fermerModaleAjout(): void {
    this.modaleAjoutOuverte = false;
  }

  onFichierSelectionne(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.fichierSelectionne = input.files && input.files.length > 0 ? input.files[0] : null;
  }

  ajouterDocument(): void {
    if (this.ajoutEnCours) {
      return;
    }

    this.messagesErreursAjout = [];

    const messages: string[] = [];
    if (this.ajoutForm.get('nom')?.errors?.['required']) {
      messages.push('Remplissez le nom du document.');
    }
    if (!this.fichierSelectionne) {
      messages.push('Sélectionnez un fichier.');
    }
    if (messages.length > 0) {
      this.messagesErreursAjout = messages;
      this.cdr.detectChanges();
      return;
    }

    const {nom} = this.ajoutForm.value;
    this.ajoutEnCours = true;

    this.documentService.ajouter(nom, this.fichierSelectionne!).subscribe({
      next: () => {
        this.ajoutEnCours = false;
        this.fermerModaleAjout();
        this.chargerDocuments();
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.ajoutEnCours = false;
        this.messagesErreursAjout = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.cdr.detectChanges();
      },
    });
  }

  demanderConfirmationSuppression(doc: DocumentAdmin): void {
    this.confirmationSuppressionEnCours = doc;
  }

  annulerConfirmationSuppression(): void {
    this.confirmationSuppressionEnCours = null;
  }

  confirmerSuppression(): void {
    if (!this.confirmationSuppressionEnCours) {
      return;
    }

    this.documentService.supprimer(this.confirmationSuppressionEnCours.id).subscribe({
      next: () => {
        this.confirmationSuppressionEnCours = null;
        this.chargerDocuments();
        this.cdr.detectChanges();
      }
    });
  }
}
