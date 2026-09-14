import {Component, inject} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {Router} from '@angular/router';
import {AuthService} from '../services/auth.service';

@Component({
  imports: [FormsModule],
  selector: 'app-connexion-component',
  styleUrl: './connexion-component.css',
  templateUrl: './connexion-component.html',
})
export class ConnexionComponent {
  private authService = inject(AuthService);
  private router = inject(Router);

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
        if (err.status === 401) {
          this.messageErreur = 'Email ou mot de passe incorrect.';
        } else if (err.status === 403) {
          this.messageErreur = 'Votre inscription est en attente de validation par un administrateur.';
        } else {
          this.messageErreur = 'Une erreur est survenue, réessaie plus tard.';
        }
      }
    });
  }
}
