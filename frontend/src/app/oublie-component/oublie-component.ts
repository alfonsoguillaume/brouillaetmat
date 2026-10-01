import {ChangeDetectorRef, Component, inject} from '@angular/core';
import {CommonModule} from '@angular/common';
import {HttpErrorResponse} from '@angular/common/http';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {MotDePasseOublieService} from '../services/mot-de-passe-oublie.service';

@Component({
  selector: 'app-oublie',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './oublie-component.html',
  styleUrl: './oublie-component.css'
})
export class OublieComponent {
  private fb = inject(FormBuilder);
  private motDePasseOublieService = inject(MotDePasseOublieService);
  private cdr = inject(ChangeDetectorRef);

  isSubmitted = false;
  envoiEnCours = false;
  message = '';

  forgotPasswordForm: FormGroup = this.fb.group({
    email: ['', [Validators.required, Validators.email]]
  });

  onSubmit(): void {
    if (this.envoiEnCours) {
      return;
    }

    if (this.forgotPasswordForm.invalid) {
      this.forgotPasswordForm.markAllAsTouched();
      return;
    }

    this.envoiEnCours = true;

    this.motDePasseOublieService.demanderReinitialisation(this.forgotPasswordForm.value.email).subscribe({
      next: (data) => {
        this.envoiEnCours = false;
        this.isSubmitted = true;
        this.message = data.message;
        this.cdr.detectChanges();
      },
      error: (err: HttpErrorResponse) => {
        this.envoiEnCours = false;
        this.isSubmitted = true;

        if (err.status === 429) {
          // anti-spam : ici on peut afficher le vrai message
          this.message = err.error?.message ?? 'Trop de tentatives. Réessayez plus tard.';
        } else {
          // message identique exprès, pour pas confirmer si le compte existe
          this.message = 'Si cette adresse est associée à un compte, un e-mail de réinitialisation vient d\'être envoyé.';
        }

        this.cdr.detectChanges();
      },
    });
  }
}
