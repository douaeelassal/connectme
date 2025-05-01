import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { User } from '../models/user.model';

@Injectable({
  providedIn: 'root'
})
export class FriendService {
  private apiUrl = 'http://localhost:8000/api_gateway.php';

  constructor(private http: HttpClient) { }

  getFriends(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/friends`);
  }

  getFriendRequests(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/friends/requests`);
  }

  sendFriendRequest(userId: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/friends/request/${userId}`, {});
  }

  acceptFriendRequest(requestId: number): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/friends/request/${requestId}/accept`, {});
  }

  rejectFriendRequest(requestId: number): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/friends/request/${requestId}/reject`, {});
  }

  removeFriend(userId: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/friends/${userId}`);
  }
}
