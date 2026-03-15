<template>
  <div class="app-layout">
    <header class="top-nav">
      <div class="logo">MES Dashboard</div>

      <nav class="top-nav-links">
        <router-link to="/dashboard">Dashboard</router-link>
        <router-link to="/work-orders">Work Orders</router-link>
        <router-link to="/defects">Defects</router-link>
        <router-link to="/downtime">Downtime</router-link>
      </nav>
    </header>

    <main class="main-content">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.app-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* 상단 네비 */
.top-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 24px;
  border-bottom: 1px solid #eee;
  background-color: #fafafa;
}

.logo {
  font-weight: 600;
  font-size: 16px;
}

.top-nav-links {
  display: flex;
  gap: 16px;
}

.top-nav a {
  text-decoration: none;
  color: #555;
  font-size: 14px;
}

.top-nav a.router-link-active {
  font-weight: 600;
  color: #1976d2;
  border-bottom: 2px solid #1976d2;
}

/* 메인 영역 */
.main-content {
  width: 1280px;
  flex: 1;
  padding: 16px 24px;
  background-color: #f5f5f5;
}

</style>
