import {ChangeDetectorRef, Component, inject} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {Router, RouterLink} from '@angular/router';
import {AuthService} from '../services/auth.service';

@Component({
  imports: [FormsModule, RouterLink],
  selector: 'app-connexion-component',
  styleUrl: './connexion-component.css',
  templateUrl: './connexion-component.html',
})
export class ConnexionComponent {
  private authService = inject(AuthService);
  private router = inject(Router);
  private cdr = inject(ChangeDetectorRef);

  email: string = '';
  password: string = '';
  messageErreur: string = '';

  onSubmit(): void {
    this.messageErreur = '';

    this.authService.login(this.email, this.password).subscribe({
      next: () => {
        this.router.navigate(['/']);
      },
      error: (err) => {
        const message = err.error?.message ?? 'Une erreur est survenue, réessaie plus tard.';
        this.messageErreur = message === 'Invalid credentials.'
          ? 'Email ou mot de passe incorrect.'
          : message;
        this.cdr.detectChanges();
      }
    });
  }
}
