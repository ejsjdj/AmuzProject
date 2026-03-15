<template>
  <main class="page">
    <header class="page-header">
      <h1>불량 관리 (Defects)</h1>
      <div class="actions">
        <button @click="openCreate">불량 추가</button>
        <button @click="exportExcel">Excel Export</button>
        <label class="import-btn">
          Excel Import
          <input type="file" accept=".xlsx,.csv" @change="importExcel" hidden />
        </label>
      </div>
    </header>

    <section class="filters">
      <!-- 불량 ID -->
      <input
        v-model="searchDefectId"
        @keyup.enter="onFilterChange"
        placeholder="불량 ID 검색"
      />

      <!-- 작업지시 ID -->
      <input
        v-model="searchWorkOrder"
        @keyup.enter="onFilterChange"
        placeholder="작업지시 ID 검색"
      />

      <!-- 서버 페이지네이션: 페이지 사이즈 선택 -->
      <label class="page-size">
        페이지 당
        <select v-model.number="pageSize" @change="onPageSizeChange">
          <option v-for="n in pageSizeOptions" :key="n" :value="n">
            {{ n }}
          </option>
        </select>
        개
      </label>
    </section>

    <section class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th @click="changeSort('defectId')">
              Defect ID
              <span v-if="sortKey === 'defectId'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('occurredAt')">
              발생시각
              <span v-if="sortKey === 'occurredAt'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('workOrderId')">
              WorkOrder ID
              <span v-if="sortKey === 'workOrderId'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('lineId')">
              Line
              <span v-if="sortKey === 'lineId'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('stationId')">
              Station
              <span v-if="sortKey === 'stationId'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('defectCode')">
              Defect Code
              <span v-if="sortKey === 'defectCode'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('quantity')">
              수량
              <span v-if="sortKey === 'quantity'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('operatorId')">
              Operator ID
              <span v-if="sortKey === 'operatorId'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('lotNo')">
              Lot No
              <span v-if="sortKey === 'lotNo'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('note')">
              비고
              <span v-if="sortKey === 'note'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>

            <th>수정</th>
            <th>삭제</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="df in defects" :key="df.defectId">
            <td>{{ df.defectId }}</td>
            <td>{{ df.occurredAt }}</td>
            <td>{{ df.workOrderId }}</td>
            <td>{{ df.lineId }}</td>
            <td>{{ df.stationId }}</td>
            <td>{{ df.defectCode }}</td>
            <td>{{ df.qty ?? df.quantity }}</td>
            <td>{{ df.operatorId }}</td>
            <td>{{ df.lotNo }}</td>
            <td>{{ df.note }}</td>

            <td>
              <button @click="openEdit(df)">수정</button>
            </td>
            <td>
              <button @click="remove(df)">삭제</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="!loading && defects.length === 0" class="empty">
        데이터가 없습니다.
      </div>
      <div v-if="loading">불러오는 중...</div>
      <div v-if="error" class="error">{{ error }}</div>

      <!-- 서버 페이지네이션 -->
      <div
        class="pagination"
        v-if="!loading && totalCount > 0"
      >
        <button :disabled="currentPage === 1" @click="goPrev">
          이전
        </button>
        <span>
          {{ currentPage }} / {{ totalPages }} 페이지
          (총 {{ totalCount }}건)
        </span>
        <button :disabled="currentPage === totalPages" @click="goNext">
          다음
        </button>
      </div>
    </section>

    <!-- 모달 -->
    <section v-if="showModal" class="modal-backdrop">
      <div class="modal">
        <h2>{{ isEdit ? '불량 수정' : '불량 추가' }}</h2>
        <form @submit.prevent="submitForm">
          <div class="field">
            <label>발생시각(occurredAt)</label>
            <input
              v-model="form.occurredAt"
              placeholder="2026-01-10T10:00:00+09:00"
              required
            />
          </div>
          <div class="field">
            <label>WorkOrder ID</label>
            <input v-model="form.workOrderId" required />
          </div>
          <div class="field">
            <label>Line ID</label>
            <input v-model="form.lineId" required />
          </div>
          <div class="field">
            <label>Station ID</label>
            <input v-model="form.stationId" required />
          </div>
          <div class="field">
            <label>Defect Code</label>
            <input v-model="form.defectCode" required />
          </div>
          <div class="field">
            <label>Operator ID</label>
            <input v-model="form.operatorId" required />
          </div>
          <div class="field">
            <label>수량(qty)</label>
            <input v-model.number="form.qty" type="number" min="1" required />
          </div>
          <div class="field">
              <label>Lot No</label>
              <input v-model="form.lotNo" />
          </div>
          <div class="field">
            <label>비고(note)</label>
            <input v-model="form.note" />
          </div>

          <div class="actions">
            <button type="submit">저장</button>
            <button type="button" @click="closeModal">취소</button>
          </div>
        </form>
      </div>
    </section>
  </main>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../api/client';

const defects = ref([]);
const loading = ref(false);
const error = ref(null);

// 필터 상태
const searchDefectId = ref('');
const searchWorkOrder = ref('');

// 정렬 상태
const sortKey = ref('occurredAt'); // 기본 정렬 컬럼
const sortOrder = ref('desc');     // 기본 정렬 방향

// 서버 페이지네이션 상태
const currentPage = ref(1);
const pageSize = ref(20);
const pageSizeOptions = [10, 20, 50, 100];
const totalPages = ref(1);
const totalCount = ref(0);

// 모달 상태
const showModal = ref(false);
const isEdit = ref(false);
const currentId = ref(null);
const form = ref({
  occurredAt: '',
  workOrderId: '',
  lineId: '',
  stationId: '',
  defectCode: '',
  qty: 1,
  note: '',
});

// 공통으로 쓸 params 생성 함수
function buildQueryParams() {
  return {
    page: currentPage.value,
    perPage: pageSize.value,
    sortBy: sortKey.value || 'occurredAt',
    sortDir: sortOrder.value || 'desc',
    defectId: searchDefectId.value,
    workOrderId: searchWorkOrder.value,
    // 나중에 lineId, stationId, defectCode, from, to 도 여기 추가
  };
}

// 서버에서 불량 목록 가져오기
async function fetchDefects() {
  loading.value = true;
  error.value = null;
  try {
    const res = await api.get('/defects', {
      params: buildQueryParams(),
    });
    defects.value = res.data.data || [];

    const meta = res.data.meta || {};
    currentPage.value = meta.page || 1;
    pageSize.value = meta.perPage || pageSize.value;
    totalPages.value = meta.totalPages || 1;
    totalCount.value = meta.total || defects.value.length;
  } catch (e) {
    console.error(e);
    error.value = e?.message || '불량 목록을 불러오지 못했습니다.';
  } finally {
    loading.value = false;
  }
}

function onFilterChange() {
  currentPage.value = 1;
  fetchDefects();
}

function onPageSizeChange() {
  currentPage.value = 1;
  fetchDefects();
}

function goPrev() {
  if (currentPage.value === 1) return;
  currentPage.value -= 1;
  fetchDefects();
}

function goNext() {
  if (currentPage.value === totalPages.value) return;
  currentPage.value += 1;
  fetchDefects();
}

// 헤더 클릭 시 정렬 변경
function changeSort(key) {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortOrder.value = key === 'occurredAt' ? 'desc' : 'asc';
  }
  currentPage.value = 1;
  fetchDefects();
}

function openCreate() {
  isEdit.value = false;
  currentId.value = null;
  form.value = {
    occurredAt: '',
    workOrderId: '',
    lineId: '',
    stationId: '',
    defectCode: '',
    operatorId: '',
    qty: 1,
    lotNo: '',
    note: '',
  };
  showModal.value = true;
}

function openEdit(df) {
  isEdit.value = true;
  currentId.value = df.defectId;
  form.value = {
    occurredAt: df.occurredAt,
    workOrderId: df.workOrderId,
    lineId: df.lineId,
    stationId: df.stationId,
    defectCode: df.defectCode,
    operatorId: df.operatorId ?? '',
    qty: df.qty ?? df.quantity ?? 1,
    lotNo: df.lotNo ?? '',
    note: df.note ?? '',
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

async function submitForm() {
  try {
    const payload = {
      occurredAt: form.value.occurredAt,
      workOrderId: form.value.workOrderId,
      lineId: form.value.lineId,
      stationId: form.value.stationId,
      defectCode: form.value.defectCode,
      qty: form.value.qty,
      operatorId: form.value.operatorId || null, // 추가
      lotNo: form.value.lotNo || null,          // 추가
      note: form.value.note,
    };

    if (isEdit.value && currentId.value) {
      await api.put(`/defects/${currentId.value}`, payload);
    } else {
      await api.post('/defects', payload);
    }
    showModal.value = false;
    await fetchDefects();
  } catch (e) {
    console.error(e);
    alert('저장 중 오류가 발생했습니다.');
  }
}

async function remove(df) {
  if (!confirm(`불량 ${df.defectId} 을(를) 삭제할까요?`)) return;
  try {
    await api.delete(`/defects/${df.defectId}`);
    await fetchDefects();
  } catch (e) {
    console.error(e);
    alert('삭제 중 오류가 발생했습니다.');
  }
}

// Export – 현재 화면 상태(필터 + 정렬 + 페이지) 그대로 Export
async function exportExcel() {
  try {
    const res = await api.get('/defects/export', {
      params: buildQueryParams(),
      responseType: 'blob',
    });
    const url = window.URL.createObjectURL(new Blob([res.data]));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'defects.xlsx';
    a.click();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    console.error(e);
    alert('Export 중 오류가 발생했습니다.');
  }
}

async function importExcel(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);

  try {
    const res = await api.post('/defects/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    console.log('import result:', res.data);

    const { created, updated, errors } = res.data.data;

    // 에러 상세를 콘솔에서 확인
    console.table(errors);

    alert(`Import 완료
    생성: ${created}
    수정: ${updated}
    에러행: ${errors.length}`);
    await fetchDefects();
  } catch (e) {
    console.error('import error:', e.response?.data || e);
    const msg =
      e?.response?.data?.error?.message ??
      'Import 중 오류가 발생했습니다. 형식을 확인해주세요.';
    alert(msg);
  } finally {
    event.target.value = '';
  }
}

onMounted(fetchDefects);
</script>

<style scoped>
.page {
  padding: 16px;
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.actions button,
.import-btn {
  margin-left: 8px;
}
.import-btn {
  display: inline-block;
  padding: 4px 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
  cursor: pointer;
}
.filters {
  margin: 12px 0;
  display: flex;
  gap: 8px;
  align-items: center;
}
.filters input {
  padding: 4px 8px;
  width: 200px;
}
.page-size {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
}
.table-wrapper {
  margin-top: 8px;
}
table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
th,
td {
  border: 1px solid #ddd;
  padding: 6px 8px;
  text-align: left;
}
th {
  background: #f5f5f5;
  cursor: pointer;
  user-select: none;
}
.empty {
  margin-top: 8px;
  color: #777;
}
.error {
  color: red;
  margin-top: 8px;
}
.pagination {
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}
/* 모달 */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal {
  background: #fff;
  padding: 16px;
  border-radius: 8px;
  width: 340px;
}
.field {
  margin-bottom: 8px;
}
.field label {
  display: block;
  font-size: 12px;
  margin-bottom: 2px;
}
.field input {
  width: 100%;
  padding: 4px 6px;
  box-sizing: border-box;
}
.modal .actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 8px;
}
.modal .actions button {
  margin-left: 8px;
}
</style>
