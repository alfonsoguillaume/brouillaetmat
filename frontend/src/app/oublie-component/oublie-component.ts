import {Component} from '@angular/core';
import {CommonModule} from '@angular/common';
import {FormBuilder, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';

@Component({
  selector: 'app-oublie',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './oublie-component.html',
  styleUrl: './oublie-component.css'
})
export class OublieComponent {
  forgotPasswordForm: FormGroup;
  isSubmitted = false;

  constructor(private fb: FormBuilder) {
    this.forgotPasswordForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]]
    });
  }

  onSubmit(): void {
    if (this.forgotPasswordForm.valid) {
      console.log('Demande envoyée pour :', this.forgotPasswordForm.value.email);
      this.isSubmitted = true;
    } else {
      this.forgotPasswordForm.markAllAsTouched();
    }
  }
}
