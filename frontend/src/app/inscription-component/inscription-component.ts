import {ChangeDetectorRef, Component, inject} from '@angular/core';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {HttpClient} from '@angular/common/http';
import {Router} from '@angular/router';

@Component({
  selector: 'app-inscription',
  standalone: true,
  imports: [ReactiveFormsModule],
  templateUrl: './inscription-component.html',
  styleUrl: './inscription-component.css'
})
export class InscriptionComponent {
  private fb = inject(FormBuilder);
  private http = inject(HttpClient);
  private router = inject(Router);
  private cdr = inject(ChangeDetectorRef);

  private apiUrl = 'http://localhost:8000/api';

  isMineur = false;
  messageErreur = '';

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
    this.messageErreur = '';

    if (!this.inscriptionForm.valid) {
      return;
    }

    const valeurs = this.inscriptionForm.value;

    if (valeurs.password !== valeurs.confirmPassword) {
      this.messageErreur = 'Les mots de passe ne correspondent pas.';
      return;
    }

    // Conversion camelCase (Angular) -> snake_case (attendu par l'API Symfony)
    const payload = {
      nom: valeurs.nom,
      prenom: valeurs.prenom,
      pseudo: valeurs.pseudo,
      email: valeurs.email,
      date_naissance: valeurs.dateNaissance,
      email_tuteur: valeurs.emailTuteur || null,
      telephone: valeurs.telephone || null,
      password: valeurs.password
    };

    this.http.post(`${this.apiUrl}/inscription`, payload).subscribe({
      next: () => {
        this.router.navigate(['/connexion']);
      },
      error: (err) => {
        this.messageErreur = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.cdr.detectChanges();
      }
    });
  }
}
