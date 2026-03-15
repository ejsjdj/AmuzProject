<template>
  <section class="filter-bar">
    <div class="fields">
      <!-- 기간 입력 한 묶음 -->
      <div class="field range-field">
        <label>기간</label>
        <div class="range-input">
          <input type="date" v-model="localFrom" />
          <span class="range-sep">~</span>
          <input type="date" v-model="localTo" />
        </div>
      </div>

      <div class="field">
        <label>Line ID</label>
        <input v-model="localLineId" placeholder="예: L1, L2" />
      </div>
    </div>

    <div class="actions">
      <button class="btn ghost" type="button" @click="reset">
        초기화
      </button>
      <button class="btn primary" type="button" @click="apply">
        적용
      </button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
  from: string;
  to: string;
  lineId: string | null;
}>();

const emit = defineEmits<{
  'update:from': [value: string];
  'update:to': [value: string];
  'update:lineId': [value: string | null];
  apply: [];
}>();

const localFrom = ref(props.from || '2026-01-01');
const localTo = ref(props.to || '2026-01-07');
const localLineId = ref(props.lineId ?? '');

watch(
  () => props.from,
  v => {
    localFrom.value = v;
  },
);
watch(
  () => props.to,
  v => {
    localTo.value = v;
  },
);
watch(
  () => props.lineId,
  v => {
    localLineId.value = v ?? '';
  },
);

function apply() {
  emit('update:from', localFrom.value);
  emit('update:to', localTo.value);
  emit('update:lineId', localLineId.value || null);
  emit('apply');
}

function reset() {
  localFrom.value = '2026-01-01';
  localTo.value = '2026-01-07';
  localLineId.value = '';
  apply();
}
</script>

<style scoped>
.filter-bar {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-end;
  flex-wrap: wrap;
}

/* 입력 필드 묶음 */
.fields {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 12px;
}

.field {
  display: flex;
  flex-direction: column;
  font-size: 12px;
}

.field label {
  margin-bottom: 3px;
  color: #555;
}

/* 기간 필드 */
.range-field {
  min-width: 260px;
}

.range-input {
  display: flex;
  align-items: center;
  gap: 4px;
}

.range-input input[type="date"] {
  flex: 1;
}

/* 공통 input 스타일 */
.field input {
  padding: 6px 8px;
  border-radius: 6px;
  border: 1px solid #d0d0d0;
  font-size: 13px;
}

.range-sep {
  font-size: 13px;
  color: #777;
}

/* 버튼 영역 */
.actions {
  display: flex;
  gap: 8px;
}

/* 공통 버튼 스타일 */
.btn {
  border-radius: 6px;
  padding: 6px 14px;
  font-size: 13px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
  white-space: nowrap;
}

/* 적용 버튼 (강조) */
.btn.primary {
  background-color: #1976d2;
  color: #ffffff;
  border-color: #1976d2;
}
.btn.primary:hover {
  background-color: #1565c0;
  border-color: #1565c0;
}

/* 초기화 버튼 (연한 테두리) */
.btn.ghost {
  background-color: #ffffff;
  color: #555;
  border-color: #d0d0d0;
}
.btn.ghost:hover {
  background-color: #f5f5f5;
}
</style>
