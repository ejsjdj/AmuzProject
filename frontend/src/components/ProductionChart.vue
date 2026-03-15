<template>
  <section class="card">
    <div class="card-header">
      <h2>시간대별 생산량</h2>
      <span v-if="!loading && series.length" class="card-subtitle">
        {{ props.from }} ~ {{ props.to }}
      </span>
    </div>

    <div v-if="loading" class="state">불러오는 중...</div>
    <div v-else-if="error" class="state error">{{ error }}</div>
    <div v-else-if="series.length === 0" class="state empty">
      데이터가 없습니다.
    </div>
    <div v-else class="chart-wrapper">
      <apexchart
        type="line"
        height="280"
        :options="chartOptions"
        :series="series"
      />
    </div>
  </section>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import api from '../api/client';

const props = defineProps({
  from: { type: String, required: true },
  to: { type: String, required: true },
  lineId: { type: String, default: null },
  reloadKey: { type: Number, required: true },
});

const loading = ref(false);
const error = ref(null);
const categories = ref([]);
const series = ref([]);

async function fetchChart() {
  loading.value = true;
  error.value = null;

  try {
    const res = await api.get('/dashboard/production', {
      params: {
        from: props.from,
        to: props.to,
        lineId: props.lineId || null,
      },
    });

    const items = res.data.data.byHour || [];

    categories.value = items.map(row => row.time.slice(11)); // "HH:MM"

    series.value = [
      {
        name: '양품 수량',
        data: items.map(row => row.goodQty),
      },
      {
        name: '불량 수량',
        data: items.map(row => row.scrapQty),
      },
    ];
  } catch (e) {
    error.value = e?.message || '생산 차트 데이터를 불러오지 못했습니다.';
  } finally {
    loading.value = false;
  }
}

watch(
  () => props.reloadKey,
  () => {
    fetchChart();
  },
  { immediate: true },
);

const chartOptions = computed(() => ({
  chart: {
    toolbar: { show: false },
    zoom: { enabled: false },
  },
  xaxis: {
    categories: categories.value,
    labels: { rotate: -45 },
  },
  yaxis: {
    title: { text: '수량' },
  },
  stroke: {
    curve: 'smooth',
  },
  dataLabels: {
    enabled: false,
  },
  tooltip: {
    shared: true,
    intersect: false,
  },
  legend: {
    position: 'top',
    horizontalAlign: 'right',
  },
  colors: ['#1976d2', '#e53935'],
}));
</script>

<style scoped>
.card {
  padding: 12px 16px;
  border-radius: 8px;
  border: 1px solid #e0e0e0;
  background: #ffffff;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  display: flex;
  flex-direction: column;
  min-height: 280px;
}

.card-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 4px;
}

.card-header h2 {
  font-size: 15px;
  font-weight: 600;
  margin: 0;
}

.card-subtitle {
  font-size: 11px;
  color: #888;
}

/* 상태 메시지 */
.state {
  margin-top: 12px;
  font-size: 13px;
  color: #555;
}
.state.error {
  color: #d32f2f;
}
.state.empty {
  color: #777;
}

.chart-wrapper {
  margin-top: 8px;
  flex: 1;
}
</style>
