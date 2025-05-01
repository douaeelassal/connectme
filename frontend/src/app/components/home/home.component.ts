import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { PostService } from '../../services/post.service';
import { AuthService } from '../../services/auth.service';
import { Post } from '../../models/post.model';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.scss']
})
export class HomeComponent implements OnInit {
  posts: Post[] = [];
  postForm: FormGroup;
  loading = false;
  error = '';
  submitting = false;
  selectedFile: File | null = null;
  previewUrl: string | null = null;

  constructor(
    private postService: PostService,
    private formBuilder: FormBuilder,
    public authService: AuthService
  ) {
    this.postForm = this.formBuilder.group({
      content: ['', Validators.required],
      media_type: ['text']
    });
  }

  ngOnInit(): void {
    this.loadPosts();
  }

  loadPosts(): void {
    this.loading = true;
    this.postService.getPosts()
      .subscribe({
        next: (response) => {
          this.posts = response.posts;
          this.loading = false;
        },
        error: (error) => {
          this.error = 'Error loading posts: ' + error.message;
          this.loading = false;
        }
      });
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;
    
    this.selectedFile = input.files[0];
    
    // Create preview for images
    if (this.selectedFile.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = () => {
        this.previewUrl = reader.result as string;
      };
      reader.readAsDataURL(this.selectedFile);
    } else {
      this.previewUrl = null;
    }
  }
  
  removeSelectedFile(): void {
    this.selectedFile = null;
    this.previewUrl = null;
  }

  createPost(): void {
    if (this.postForm.invalid) {
      return;
    }

    this.submitting = true;
    this.postService.createPost(
      this.postForm.get('content')?.value, 
      this.selectedFile || undefined
    ).subscribe({
      next: () => {
        this.postForm.reset({media_type: 'text'});
        this.selectedFile = null;
        this.previewUrl = null;
        this.loadPosts();
        this.submitting = false;
      },
      error: (error) => {
        this.error = 'Error creating post: ' + error.message;
        this.submitting = false;
      }
    });
  }

  likePost(post: Post): void {
    if (post.liked_by_me) {
      this.postService.unlikePost(post.id).subscribe({
        next: () => this.loadPosts(),
        error: (error) => this.error = 'Error unliking post: ' + error.message
      });
    } else {
      this.postService.likePost(post.id).subscribe({
        next: () => this.loadPosts(),
        error: (error) => this.error = 'Error liking post: ' + error.message
      });
    }
  }
}
