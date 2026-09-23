import {ChangeDetectorRef, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {MotDePasseOublieService} from '../services/mot-de-passe-oublie.service';
import {motDePasseValidator} from '../validators/champs.validators';

@Component({
  selector: 'app-reinitialiser-mot-de-passe-component',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './reinitialiser-mot-de-passe-component.html',
  styleUrl: './reinitialiser-mot-de-passe-component.css',
})
export class ReinitialiserMotDePasseComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private fb = inject(FormBuilder);
  private motDePasseOublieService = inject(MotDePasseOublieService);
  private cdr = inject(ChangeDetectorRef);

  private token = '';
  lienIncomplet = false;

  afficherMotDePasse = false;
  envoiEnCours = false;
  succes = false;
  messagesErreurs: string[] = [];

  motDePasseForm: FormGroup = this.fb.group({
    nouveauMotDePasse: ['', [Validators.required, motDePasseValidator]],
    confirmationNouveauMotDePasse: ['', Validators.required],
  });

  ngOnInit(): void {
    const token = this.route.snapshot.paramMap.get('token');
    if (!token) {
      this.lienIncomplet = true;
      return;
    }
    this.token = token;
  }

  reinitialiser(): void {
    if (this.envoiEnCours) {
      return;
    }

    this.messagesErreurs = [];

    const controlePassword = this.motDePasseForm.get('nouveauMotDePasse');

    if (!this.motDePasseForm.valid) {
      const messages: string[] = [];

      if (controlePassword?.errors?.['required']) {
        messages.push('Remplissez le nouveau mot de passe.');
      } else if (controlePassword?.errors) {
        if (controlePassword.errors['longueur']) messages.push('Le nouveau mot de passe doit avoir au moins 12 caractères.');
        if (controlePassword.errors['majuscule']) messages.push('Le nouveau mot de passe doit avoir au moins une majuscule.');
        if (controlePassword.errors['minuscule']) messages.push('Le nouveau mot de passe doit avoir au moins une minuscule.');
        if (controlePassword.errors['chiffre']) messages.push('Le nouveau mot de passe doit avoir au moins un chiffre.');
        if (controlePassword.errors['special']) messages.push('Le nouveau mot de passe doit avoir au moins un caractère spécial.');
      }
      if (this.motDePasseForm.get('confirmationNouveauMotDePasse')?.errors?.['required']) {
        messages.push('Remplissez la vérification du mot de passe.');
      }

      this.messagesErreurs = messages;
      this.cdr.detectChanges();
      return;
    }

    const valeurs = this.motDePasseForm.value;

    if (valeurs.nouveauMotDePasse !== valeurs.confirmationNouveauMotDePasse) {
      this.messagesErreurs = ['Les deux mots de passe ne sont pas identiques.'];
      this.cdr.detectChanges();
      return;
    }

    this.envoiEnCours = true;

    this.motDePasseOublieService.reinitialiserMotDePasse(this.token, valeurs.nouveauMotDePasse).subscribe({
      next: () => {
        this.envoiEnCours = false;
        this.succes = true;
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.envoiEnCours = false;
        this.messagesErreurs = [err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.'];
        this.cdr.detectChanges();
      },
    });
  }
}
