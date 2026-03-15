<template>
  <main class="dashboard">
    <header class="dashboard-header">
      <div>
        <h1>MES 대시보드</h1>
        <p class="subtitle">조립 공정 생산·품질·설비 현황</p>
      </div>

      <div class="header-filters">
        <!-- 필요하면 상단 오른쪽에 요약/리셋 버튼 등 -->
        <!-- <button @click="resetFilters">필터 초기화</button> -->
      </div>
    </header>

    <!-- 상단 필터 바 -->
    <section class="filter-section">
      <DashboardFilterBar
        v-model:from="from"
        v-model:to="to"
        v-model:lineId="lineId"
        @apply="reloadAll"
      />
    </section>

    <!-- KPI 영역 -->
    <section class="kpi-section">
      <DashboardKpi
        :from="from"
        :to="to"
        :line-id="lineId"
        :reload-key="reloadKey"
      />
    </section>

    <!-- 중간 그래프 영역 -->
    <section class="middle-row">
      <div class="panel">
        <div class="panel-header">
          <h2>시간대별 생산량</h2>
        </div>
        <div class="panel-body">
          <ProductionChart
            :from="from"
            :to="to"
            :line-id="lineId"
            :reload-key="reloadKey"
          />
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h2>불량 코드 파레토</h2>
        </div>
        <div class="panel-body">
          <QualityParetoChart
            :from="from"
            :to="to"
            :line-id="lineId"
            :reload-key="reloadKey"
          />
        </div>
      </div>
    </section>
  </main>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import DashboardFilterBar from '@/components/DashboardFilterBar.vue';
import DashboardKpi from '@/components/DashboardKpi.vue';
import ProductionChart from '@/components/ProductionChart.vue';
import QualityParetoChart from '@/components/QualityParetoChart.vue';

const from = ref('2026-01-01');
const to = ref('2026-01-07');
const lineId = ref<string | null>(null);

const reloadKey = ref(0);

function reloadAll() {
  reloadKey.value++;
}

// 필요하면 필터 초기화 버튼용
// function resetFilters() {
//   from.value = '2026-01-01';
//   to.value = '2026-01-07';
//   lineId.value = null;
//   reloadAll();
// }
</script>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* 상단 타이틀 */
.dashboard-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
}

.dashboard-header h1 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}

.subtitle {
  margin: 4px 0 0;
  font-size: 13px;
  color: #777;
}

/* 필터 영역 / KPI 영역 */
.filter-section {
  background-color: #ffffff;
  padding: 12px 16px;
  border-radius: 8px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}

.kpi-section {
  margin-top: 4px;
}

/* 그래프 패널 공통 스타일 */
.middle-row {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1.6fr);
  gap: 16px;
  margin-top: 4px;
}

.panel {
  background-color: #ffffff;
  border-radius: 8px;
  padding: 12px 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  display: flex;
  flex-direction: column;
  min-height: 320px;
}

.panel-header {
  margin-bottom: 8px;
}

.panel-header h2 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
}

.panel-body {
  flex: 1;
  /* 차트 컴포넌트가 꽉 차도록 */
  display: flex;
  flex-direction: column;
}
</style>
