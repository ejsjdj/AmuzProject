<template>
  <main class="page">
    <header class="page-header">
      <h1>설비 정지 관리 (Downtime)</h1>
      <div class="actions">
        <button @click="openCreate">정지 이벤트 추가</button>
        <button @click="exportExcel">Excel Export</button>
        <label class="import-btn">
          Excel Import
          <input type="file" accept=".xlsx,.csv" @change="importExcel" hidden />
        </label>
      </div>
    </header>

    <section class="filters">
      <input
        v-model="searchEventId"
        @keyup.enter="onFilterChange"
        placeholder="Event ID 검색"
      />

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
            <th @click="changeSort('eventId')">
              Event ID
              <span v-if="sortKey === 'eventId'">
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
            <th @click="changeSort('reasonCode')">
              Reason Code
              <span v-if="sortKey === 'reasonCode'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('startedAt')">
              시작
              <span v-if="sortKey === 'startedAt'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
              </span>
            </th>
            <th @click="changeSort('endedAt')">
              종료
              <span v-if="sortKey === 'endedAt'">
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
          <tr v-for="e in events" :key="e.eventId">
            <td>{{ e.eventId }}</td>
            <td>{{ e.lineId }}</td>
            <td>{{ e.stationId }}</td>
            <td>{{ e.reasonCode }}</td>
            <td>{{ e.startedAt }}</td>
            <td>{{ e.endedAt }}</td>
            <td>{{ e.note }}</td>
            <td>
              <button @click="openEdit(e)">수정</button>
            </td>
            <td>
              <button @click="remove(e)">삭제</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="!loading && events.length === 0" class="empty">
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
        <h2>{{ isEdit ? '정지 이벤트 수정' : '정지 이벤트 추가' }}</h2>
        <form @submit.prevent="submitForm">
          <div class="field">
            <label>Line ID</label>
            <input v-model="form.lineId" required />
          </div>
          <div class="field">
            <label>Station ID</label>
            <input v-model="form.stationId" required />
          </div>
          <div class="field">
            <label>Reason Code</label>
            <input v-model="form.reasonCode" required />
          </div>
          <div class="field">
            <label>시작 시간(startedAt)</label>
            <input
              v-model="form.startedAt"
              placeholder="2026-01-10T10:00:00+09:00"
              required
            />
          </div>
          <div class="field">
            <label>종료 시간(endedAt)</label>
            <input
              v-model="form.endedAt"
              placeholder="2026-01-10T10:30:00+09:00"
            />
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

const events = ref([]);
const loading = ref(false);
const error = ref(null);

// 검색
const searchEventId = ref('');
const searchLine = ref('');
const searchStation = ref('');
const searchReason = ref('');

// 정렬 상태
const sortKey = ref('startedAt'); // 기본 정렬 컬럼
const sortOrder = ref('desc');    // asc | desc

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
  lineId: '',
  stationId: '',
  reasonCode: '',
  startedAt: '',
  endedAt: '',
  note: '',
});

// 서버에서 다운타임 목록 가져오기 (필터/정렬/페이지네이션 포함)
async function fetchEvents() {
  loading.value = true;
  error.value = null;
  try {
    const res = await api.get('/downtime-events', {
      params: {
        page: currentPage.value,
        perPage: pageSize.value,
        eventId: searchEventId.value || null,
        lineId: searchLine.value || null,
        stationId: searchStation.value || null,
        reasonCode: searchReason.value || null,
        sortBy: sortKey.value,
        sortDir: sortOrder.value,
      },
    });

    events.value = res.data.data || [];

    const meta = res.data.meta || {};
    currentPage.value = meta.page || 1;
    pageSize.value = meta.perPage || pageSize.value;
    totalPages.value = meta.totalPages || 1;
    totalCount.value = meta.total || events.value.length;
  } catch (e) {
    console.error(e);
    error.value = e?.message || '다운타임 목록을 불러오지 못했습니다.';
  } finally {
    loading.value = false;
  }
}

function onFilterChange() {
  currentPage.value = 1;
  fetchEvents();
}

function onPageSizeChange() {
  currentPage.value = 1;
  fetchEvents();
}

function goPrev() {
  if (currentPage.value === 1) return;
  currentPage.value -= 1;
  fetchEvents();
}

function goNext() {
  if (currentPage.value === totalPages.value) return;
  currentPage.value += 1;
  fetchEvents();
}

function changeSort(key) {
  if (sortKey.value === key) {
    // 같은 컬럼을 다시 클릭하면 ASC <-> DESC 토글
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    // 다른 컬럼으로 변경 시 기본 정렬 방향을 asc로 시작
    sortKey.value = key;
    sortOrder.value = 'asc';
  }
  currentPage.value = 1;
  fetchEvents();
}

function openCreate() {
  isEdit.value = false;
  currentId.value = null;
  form.value = {
    lineId: '',
    stationId: '',
    reasonCode: '',
    startedAt: '',
    endedAt: '',
    note: '',
  };
  showModal.value = true;
}

function openEdit(e) {
  isEdit.value = true;
  currentId.value = e.eventId;
  form.value = {
    lineId: e.lineId,
    stationId: e.stationId,
    reasonCode: e.reasonCode,
    startedAt: e.startedAt,
    endedAt: e.endedAt,
    note: e.note ?? '',
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

async function submitForm() {
  try {
    const payload = {
      lineId: form.value.lineId,
      stationId: form.value.stationId,
      reasonCode: form.value.reasonCode,
      startedAt: form.value.startedAt,
      endedAt: form.value.endedAt || null,
      note: form.value.note,
    };

    if (isEdit.value && currentId.value) {
      await api.put(`/downtime-events/${currentId.value}`, payload);
    } else {
      await api.post('/downtime-events', payload);
    }
    showModal.value = false;
    await fetchEvents();
  } catch (e) {
    console.error(e);
    alert('저장 중 오류가 발생했습니다.');
  }
}

async function remove(e) {
  if (!confirm(`Event ${e.eventId} 를 삭제할까요?`)) return;
  try {
    await api.delete(`/downtime-events/${e.eventId}`);
    await fetchEvents();
  } catch (err) {
    console.error(err);
    alert('삭제 중 오류가 발생했습니다.');
  }
}

// 서버 페이지네이션 기준 Export
async function exportExcel() {
  try {
    const res = await api.get('/downtime-events/export', {
      params: {
        // 검색 상태
        eventId: searchEventId.value || null,
        lineId: searchLine.value || null,
        stationId: searchStation.value || null,
        reasonCode: searchReason.value || null,

        // 정렬 상태
        sortBy: sortKey.value,
        sortDir: sortOrder.value,

        // (선택) 현재 페이지 데이터만 Export 하고 싶으면 주석 해제
        page: currentPage.value,
        perPage: pageSize.value,
      },
      responseType: 'blob',
    });

    const url = window.URL.createObjectURL(new Blob([res.data]));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'downtime-events.xlsx';
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
    await api.post('/downtime-events/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    alert('Import 완료');
    await fetchEvents();
  } catch (e) {
    console.error(e);
    alert('Import 중 오류가 발생했습니다.');
  } finally {
    event.target.value = '';
  }
}

onMounted(fetchEvents);
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
  width: 360px;
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
