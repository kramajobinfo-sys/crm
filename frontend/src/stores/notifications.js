import { defineStore } from 'pinia';
import http from '@/services/http';

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    items: [],
    unreadCount: 0,
    loading: false,
  }),

  actions: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await http.get('/notifications');
        this.items = data.data.items;
        this.unreadCount = data.data.unread_count;
      } finally {
        this.loading = false;
      }
    },

    async markRead(id) {
      await http.post(`/notifications/${id}/read`);
      const n = this.items.find((x) => x.id === id);
      if (n && !n.read) {
        n.read = true;
        this.unreadCount = Math.max(0, this.unreadCount - 1);
      }
    },

    async markAllRead() {
      await http.post('/notifications/read-all');
      this.items.forEach((n) => (n.read = true));
      this.unreadCount = 0;
    },
  },
});
