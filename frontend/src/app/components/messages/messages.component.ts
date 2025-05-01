import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { MessageService } from '../../services/message.service';

@Component({
  selector: 'app-messages',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './messages.component.html',
  styleUrls: ['./messages.component.scss']
})
export class MessagesComponent implements OnInit {
  conversations: any[] = [];
  currentConversation: any = null;
  activeUserId: number | null = null;
  messages: any[] = [];
  newMessage = '';
  selectedFile: File | null = null;
  previewUrl: string | null = null;
  
  loading = {
    conversations: false,
    messages: false,
    sending: false
  };
  
  error = '';

  constructor(
    private messageService: MessageService,
    private route: ActivatedRoute,
    private router: Router
  ) { }

  ngOnInit(): void {
    this.loadConversations();
    
    this.route.params.subscribe(params => {
      if (params['id']) {
        this.activeUserId = +params['id'];
        this.loadMessages(this.activeUserId);
      }
    });
  }

  loadConversations(): void {
    this.loading.conversations = true;
    this.messageService.getConversations().subscribe({
      next: (response) => {
        this.conversations = response.conversations;
        this.loading.conversations = false;
        
        // If no active user but we have conversations, select the first one
        if (!this.activeUserId && this.conversations.length > 0) {
          this.activeUserId = this.conversations[0].user.id;
          this.router.navigate(['/messages', this.activeUserId]);
        }
      },
      error: (error) => {
        this.error = 'Error loading conversations: ' + error.message;
        this.loading.conversations = false;
      }
    });
  }

  loadMessages(userId: number): void {
    this.loading.messages = true;
    this.messageService.getConversation(userId).subscribe({
      next: (response) => {
        this.messages = response.messages;
        this.loading.messages = false;
        
        // Update current conversation
        this.currentConversation = this.conversations.find(c => c.user.id === userId);
        
        // Scroll to bottom of messages
        setTimeout(() => {
          this.scrollToBottom();
        }, 100);
      },
      error: (error) => {
        this.error = 'Error loading messages: ' + error.message;
        this.loading.messages = false;
      }
    });
  }

  sendMessage(): void {
    if (!this.newMessage.trim() && !this.selectedFile) return;
    if (!this.activeUserId) return;
    
    this.loading.sending = true;
    this.messageService.sendMessage(
      this.activeUserId, 
      this.newMessage,
      this.selectedFile || undefined
    ).subscribe({
      next: () => {
        this.newMessage = '';
        this.selectedFile = null;
        this.previewUrl = null;
        this.loadMessages(this.activeUserId!);
        this.loadConversations(); // Refresh conversation list for unread counts
        this.loading.sending = false;
      },
      error: (error) => {
        this.error = 'Error sending message: ' + error.message;
        this.loading.sending = false;
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

  selectConversation(userId: number): void {
    this.router.navigate(['/messages', userId]);
  }

  scrollToBottom(): void {
    const messagesContainer = document.querySelector('.messages-container');
    if (messagesContainer) {
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
  }

  formatTime(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  isToday(dateString: string): boolean {
    const date = new Date(dateString);
    const today = new Date();
    return date.getDate() === today.getDate() &&
      date.getMonth() === today.getMonth() &&
      date.getFullYear() === today.getFullYear();
  }

  formatDate(dateString: string): string {
    const date = new Date(dateString);
    if (this.isToday(dateString)) {
      return 'Today';
    }
    
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    if (date.getDate() === yesterday.getDate() &&
        date.getMonth() === yesterday.getMonth() &&
        date.getFullYear() === yesterday.getFullYear()) {
      return 'Yesterday';
    }
    
    return date.toLocaleDateString();
  }
}
