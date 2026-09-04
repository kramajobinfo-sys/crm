// Core API shapes + a starter set of domain types for NPCRM (Krama).
// Expand this file as you convert more services/components to TypeScript.

export type Id = number;

/** Standard success envelope returned by the Laravel API. */
export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
}

/** A page of a list endpoint. */
export interface Paginated<T> {
  data: T[];
  meta?: { current_page: number; last_page: number; per_page: number; total: number };
}

export interface Attachment {
  id: Id;
  name: string;
  mime: string;
  size: number;
  kind: 'image' | 'video' | 'audio' | 'file';
  url: string;
}

export interface Lead {
  id: Id;
  name: string;
  company_name?: string | null;
  email?: string | null;
  phone?: string | null;
  mobile?: string | null;
  status_id?: Id | null;
  source_id?: Id | null;
  owner_id?: Id | null;
  estimated_value?: number | null;
  currency?: string | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}
