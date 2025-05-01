import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

@Injectable({
  providedIn: 'root'
})
export class AuthGuard {
  constructor(
    private router: Router,
    private authService: AuthService
  ) { }

  canActivate() {
    const currentUser = this.authService.currentUserValue;
    if (currentUser) {
      // User is logged in, so allow access
      return true;
    }

    // Not logged in, redirect to login page
    this.router.navigate(['/login']);
    return false;
  }
}
