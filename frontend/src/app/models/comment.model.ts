import { User } from './user.model';

export interface Comment {
  id: number;
  content: string;
  created_at: string;
  user: User;
}
