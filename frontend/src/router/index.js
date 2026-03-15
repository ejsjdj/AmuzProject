import { createRouter, createWebHistory } from 'vue-router';
import DashboardView from '../views/DashboardView.vue';
import WorkOrdersView from '../views/WorkOrdersView.vue';
import DefectsView from '../views/DefectsView.vue';
import DowntimeView from '../views/DowntimeView.vue';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', name: 'dashboard', component: DashboardView },
    { path: '/work-orders', name: 'work-orders', component: WorkOrdersView },
    { path: '/defects', name: 'defects', component: DefectsView },
    { path: '/downtime', name: 'downtime', component: DowntimeView },
  ],
});

export default router;
