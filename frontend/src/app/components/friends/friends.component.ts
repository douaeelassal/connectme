import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FriendService } from '../../services/friend.service';
import { UserService } from '../../services/user.service';
import { User } from '../../models/user.model';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-friends',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule],
  templateUrl: './friends.component.html',
  styleUrls: ['./friends.component.scss']
})
export class FriendsComponent implements OnInit {
  friends: User[] = [];
  friendRequests: any = { received_requests: [], sent_requests: [] };
  searchResults: User[] = [];
  searchQuery = '';
  loading = {
    friends: false,
    requests: false,
    search: false
  };
  error = '';
  
  constructor(
    private friendService: FriendService,
    private userService: UserService
  ) { }

  ngOnInit(): void {
    this.loadFriends();
    this.loadFriendRequests();
  }

  loadFriends(): void {
    this.loading.friends = true;
    this.friendService.getFriends().subscribe({
      next: (response) => {
        this.friends = response.friends;
        this.loading.friends = false;
      },
      error: (error) => {
        this.error = 'Error loading friends: ' + error.message;
        this.loading.friends = false;
      }
    });
  }

  loadFriendRequests(): void {
    this.loading.requests = true;
    this.friendService.getFriendRequests().subscribe({
      next: (response) => {
        this.friendRequests = response;
        this.loading.requests = false;
      },
      error: (error) => {
        this.error = 'Error loading friend requests: ' + error.message;
        this.loading.requests = false;
      }
    });
  }

  searchUsers(): void {
    if (!this.searchQuery.trim()) {
      this.searchResults = [];
      return;
    }
    
    this.loading.search = true;
    this.userService.searchUsers(this.searchQuery).subscribe({
      next: (response) => {
        this.searchResults = response.users;
        this.loading.search = false;
      },
      error: (error) => {
        this.error = 'Error searching users: ' + error.message;
        this.loading.search = false;
      }
    });
  }

  sendFriendRequest(userId: number): void {
    this.friendService.sendFriendRequest(userId).subscribe({
      next: () => {
        this.loadFriendRequests();
        this.searchUsers(); // Refresh the search results
      },
      error: (error) => {
        this.error = 'Error sending friend request: ' + error.message;
      }
    });
  }

  acceptFriendRequest(requestId: number): void {
    this.friendService.acceptFriendRequest(requestId).subscribe({
      next: () => {
        this.loadFriends();
        this.loadFriendRequests();
      },
      error: (error) => {
        this.error = 'Error accepting friend request: ' + error.message;
      }
    });
  }

  rejectFriendRequest(requestId: number): void {
    this.friendService.rejectFriendRequest(requestId).subscribe({
      next: () => {
        this.loadFriendRequests();
      },
      error: (error) => {
        this.error = 'Error rejecting friend request: ' + error.message;
      }
    });
  }

  removeFriend(userId: number): void {
    if (confirm('Are you sure you want to remove this friend?')) {
      this.friendService.removeFriend(userId).subscribe({
        next: () => {
          this.loadFriends();
        },
        error: (error) => {
          this.error = 'Error removing friend: ' + error.message;
        }
      });
    }
  }
}
