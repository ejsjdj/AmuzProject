<template>
  <section class="kpi-wrapper">
    <div class="kpi-header">
      <h2>KPI</h2>
      <span v-if="!loading && data" class="kpi-period">
        {{ props.from }} ~ {{ props.to }}
      </span>
    </div>

    <div v-if="loading" class="kpi-state">불러오는 중...</div>
    <div v-else-if="error" class="kpi-state error">{{ error }}</div>

    <div v-else-if="data" class="kpi-grid">
      <div class="kpi-card">
        <div class="label">총 생산 수량</div>
        <div class="value">{{ data.totalProduced }}</div>
      </div>

      <div class="kpi-card">
        <div class="label">양품 / 불량</div>
        <div class="value">
          {{ data.totalGood }} / {{ data.totalScrap }}
        </div>
        <div class="sub">
          총 불량 수량: {{ data.totalDefectQty }}
        </div>
      </div>

      <div class="kpi-card warning">
        <div class="label">불량률</div>
        <div class="value">
          {{ data.defectRate }}%
        </div>
      </div>

      <div class="kpi-card highlight">
        <div class="label">OEE</div>
        <div class="value">
          {{ data.oee }}%
        </div>
        <div class="sub">
          A {{ data.availability }}% · P {{ data.performance }}% · Q
          {{ data.quality }}%
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import api from '../api/client';

const props = defineProps({
  from: { type: String, required: true },
  to: { type: String, required: true },
  lineId: { type: String, default: null },
  reloadKey: { type: Number, default: 0 },
});

interface KpiData {
  totalProduced: number;
  totalGood: number;
  totalScrap: number;
  defectRate: number;
  totalDefectQty: number;
  plannedMinutes: number;
  downtimeMinutes: number;
  runtimeMinutes: number;
  totalTargetQty: number;
  availability: number;
  performance: number;
  quality: number;
  oee: number;
}

const loading = ref(false);
const error = ref<string | null>(null);
const data = ref<KpiData | null>(null);

async function fetchKpi() {
  loading.value = true;
  error.value = null;

  try {
    const res = await api.get('/dashboard/kpi', {
      params: {
        from: props.from,
        to: props.to,
        lineId: props.lineId || null,
      },
    });

    data.value = res.data.data;
  } catch (e: any) {
    error.value = e?.message ?? 'KPI 데이터를 불러오지 못했습니다.';
  } finally {
    loading.value = false;
  }
}

watch(
  () => props.reloadKey,
  () => {
    fetchKpi();
  },
  { immediate: true },
);
</script>

<style scoped>
.kpi-wrapper {
  margin-top: 12px;
}

/* 상단 타이틀 + 기간 표시 */
.kpi-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}

.kpi-header h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
}

.kpi-period {
  font-size: 12px;
  color: #777;
}

/* 로딩/에러 상태 */
.kpi-state {
  margin-top: 8px;
  font-size: 13px;
  color: #555;
}
.kpi-state.error {
  color: #d32f2f;
}

/* KPI 카드 그리드 */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-top: 8px;
}

.kpi-card {
  padding: 12px 14px;
  border-radius: 10px;
  background: #ffffff;
  border: 1px solid #e0e0e0;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}

/* 불량률 카드 강조 (연한 붉은 배경) */
.kpi-card.warning {
  border-color: #ffcdd2;
  background: #fff5f5;
}

/* OEE 카드 강조 (연한 파란 배경) */
.kpi-card.highlight {
  border-color: #bbdefb;
  background: #f5f9ff;
}

.label {
  font-size: 12px;
  color: #666;
  margin-bottom: 4px;
}

.value {
  font-size: 22px;
  font-weight: 600;
}

.sub {
  margin-top: 4px;
  font-size: 12px;
  color: #888;
}
</style>
