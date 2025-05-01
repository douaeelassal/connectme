import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class MessageService {
  private apiUrl = 'http://localhost:8000/api_gateway.php';

  constructor(private http: HttpClient) { }

  getConversations(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/conversations`);
  }

  getConversation(userId: number): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/messages/${userId}`);
  }

  sendMessage(receiverId: number, content: string, mediaFile?: File): Observable<any> {
    const formData = new FormData();
    formData.append('receiver_id', receiverId.toString());
    formData.append('content', content);
    
    if (mediaFile) {
      formData.append('media', mediaFile);
    }
    
    return this.http.post<any>(`${this.apiUrl}/messages`, formData);
  }

  deleteMessage(messageId: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/messages/${messageId}`);
  }
}
