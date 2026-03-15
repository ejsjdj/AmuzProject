<template>
  <section class="card">
    <div class="card-header">
      <h2>불량 코드 파레토</h2>
      <span v-if="!loading && barSeries && lineSeries" class="card-subtitle">
        {{ props.from }} ~ {{ props.to }}
      </span>
    </div>

    <div v-if="loading" class="state">불러오는 중...</div>
    <div v-else-if="error" class="state error">{{ error }}</div>
    <div v-else-if="!barSeries || !lineSeries" class="state empty">
      데이터가 없습니다.
    </div>
    <div v-else class="chart-wrapper">
      <apexchart
        type="bar"
        height="280"
        :options="chartOptions"
        :series="[barSeries, lineSeries]"
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
const barSeries = ref(null);
const lineSeries = ref(null);

async function fetchChart() {
  loading.value = true;
  error.value = null;

  try {
    const res = await api.get('/dashboard/quality', {
      params: {
        from: props.from,
        to: props.to,
        lineId: props.lineId || null,
      },
    });

    const items = res.data.data.paretoByDefectCode || [];

    categories.value = items.map(
      row => `${row.defectCode} ${row.defectName}`,
    );

    const counts = items.map(row => row.qty);
    const total = counts.reduce((sum, v) => sum + v, 0) || 1;

    const cum = [];
    let acc = 0;
    for (const v of counts) {
      acc += v;
      cum.push(Math.round((acc / total) * 100));
    }

    barSeries.value = {
      name: '불량 수량',
      type: 'column',
      data: counts,
    };

    lineSeries.value = {
      name: '누적 비율(%)',
      type: 'line',
      data: cum,
    };
  } catch (e) {
    error.value =
      e?.message || '품질 파레토 차트를 불러오지 못했습니다.';
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
    stacked: false,
    toolbar: { show: false },
  },
  xaxis: {
    categories: categories.value,
  },
  yaxis: [
    {
      seriesName: '불량 수량',
      title: { text: '불량 수량' },
    },
    {
      seriesName: '누적 비율(%)',
      opposite: true,
      max: 100,
      title: { text: '누적 비율(%)' },
    },
  ],
  dataLabels: {
    enabled: false,
  },
  stroke: {
    width: [0, 2],
  },
  tooltip: {
    shared: true,
    intersect: false,
  },
  legend: {
    position: 'top',
    horizontalAlign: 'right',
  },
  colors: ['#e53935', '#1976d2'],
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
