import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { AuthService } from '../../services/auth.service';
import { User } from '../../models/user.model';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.scss']
})
export class ProfileComponent implements OnInit {
  profileForm: FormGroup;
  user: User | null = null;
  loading = false;
  error = '';
  success = '';

  constructor(
    private formBuilder: FormBuilder,
    private authService: AuthService
  ) {
    this.profileForm = this.formBuilder.group({
      name: ['', Validators.required],
      email: ['', [Validators.required, Validators.email]],
      bio: ['']
    });
  }

  ngOnInit(): void {
    this.loadUserProfile();
  }

loadUserProfile(): void {
  this.loading = true;
  this.authService.getProfile().subscribe({
    next: (response) => {
      this.user = response.user;
      if (this.user) {  // Add this check
        this.profileForm.patchValue({
          name: this.user.name,
          email: this.user.email,
          bio: this.user.bio || ''
        });
      }
      this.loading = false;
    },
    error: (error) => {
      this.error = 'Error loading profile: ' + error.message;
      this.loading = false;
    }
  });
}
  onSubmit(): void {
    if (this.profileForm.invalid) {
      return;
    }

    this.loading = true;
    this.authService.updateProfile(this.profileForm.value).subscribe({
      next: (response) => {
        this.user = response.user;
        this.success = 'Profile updated successfully!';
        this.loading = false;
      },
      error: (error) => {
        this.error = 'Error updating profile: ' + error.message;
        this.loading = false;
      }
    });
  }
}
