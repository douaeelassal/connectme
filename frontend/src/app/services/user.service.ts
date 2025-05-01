import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { User } from '../models/user.model';

@Injectable({
  providedIn: 'root'
})
export class UserService {
  private apiUrl = 'http://localhost:8000';
  private searchUrl = 'http://localhost:8000/search_endpoint.php';

  constructor(private http: HttpClient) { }

  getUser(id: number): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/api_gateway.php/users/${id}`);
  }

  searchUsers(query: string): Observable<any> {
    return this.http.get<any>(`${this.searchUrl}?search=${query}`);
  }
}
