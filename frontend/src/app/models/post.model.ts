import { User } from './user.model';
import { Comment } from './comment.model';

export interface Post {
  id: number;
  content: string;
  media_url?: string;
  media_type: string;
  created_at: string;
  updated_at?: string;
  user: User;
  comments: Comment[];
  likes: number;
  liked_by_me: boolean;
}
