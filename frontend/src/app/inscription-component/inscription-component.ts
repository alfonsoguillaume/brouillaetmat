import {Component, inject} from '@angular/core';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';

@Component({
  selector: 'app-inscription',
  standalone: true,
  imports: [ReactiveFormsModule],
  templateUrl: './inscription-component.html',
  styleUrl: './inscription-component.css'
})
export class InscriptionComponent {
  private fb = inject(FormBuilder);

  isMineur = false;

  inscriptionForm: FormGroup = this.fb.group({
    nom: ['', Validators.required],
    prenom: ['', Validators.required],
    pseudo: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    dateNaissance: ['', Validators.required],
    emailTuteur: [''],
    telephone: [''],
    password: ['', Validators.required],
    confirmPassword: ['', Validators.required]
  });

  checkAge(): void {
    const dateValeur = this.inscriptionForm.get('dateNaissance')?.value;
    if (!dateValeur) return;

    const birthDate = new Date(dateValeur);
    const today = new Date();

    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }

    this.isMineur = age < 18;

    const tuteurControl = this.inscriptionForm.get('emailTuteur');
    if (this.isMineur) {
      tuteurControl?.setValidators([Validators.required, Validators.email]);
    } else {
      tuteurControl?.clearValidators();
      tuteurControl?.setValue('');
    }
    tuteurControl?.updateValueAndValidity();
  }

  onSubmit(): void {
    if (this.inscriptionForm.valid) {
      // Traitement de l'inscription
    }
  }
}
