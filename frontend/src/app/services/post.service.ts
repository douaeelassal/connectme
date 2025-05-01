import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class PostService {
  private apiUrl = 'http://localhost:8000/api_gateway.php';

  constructor(private http: HttpClient) { }

  getPosts(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/posts`);
  }

  getPost(id: number): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/posts/${id}`);
  }

createPost(content: string, mediaFile?: File): Observable<any> {
  const formData = new FormData();
  formData.append('content', content);
  
  if (mediaFile) {
    formData.append('media', mediaFile);
    formData.append('media_type', mediaFile.type.startsWith('image/') ? 'image' : 'video');
  } else {
    formData.append('media_type', 'text');
  }
  
  return this.http.post<any>(`${this.apiUrl}/posts`, formData);
}
  updatePost(id: number, postData: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/posts/${id}`, postData);
  }

  deletePost(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/posts/${id}`);
  }

  likePost(id: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/posts/${id}/like`, {});
  }

  unlikePost(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/posts/${id}/like`);
  }

  addComment(postId: number, content: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/posts/${postId}/comments`, { content });
  }
}
