<template>
  <main class="page">
    <header class="page-header">
      <h1>작업지시 관리</h1>
      <div class="actions">
        <button @click="openCreate">작업지시 추가</button>
        <button @click="exportExcel">Excel Export</button>
        <label class="import-btn">
          Excel Import
          <input type="file" accept=".xlsx,.csv" @change="importExcel" hidden />
        </label>
      </div>
    </header>

    <section class="filters">
      <input
        v-model="search"
        @keyup.enter="onSearch"
        placeholder="작업지시 ID 검색"
      />

      <!-- 페이지 사이즈 선택 (서버에 perPage로 전달) -->
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
            <th @click="changeSort('productId')">
            Product
            <span v-if="sortKey === 'productId'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
            </span>
            </th>
            <th @click="changeSort('routingId')">
            Routing
            <span v-if="sortKey === 'routingId'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
            </span>
            </th>
            <th @click="changeSort('plannedStartAt')">
            계획 시작
            <span v-if="sortKey === 'plannedStartAt'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
            </span>
            </th>
            <th @click="changeSort('plannedEndAt')">
            계획 종료
            <span v-if="sortKey === 'plannedEndAt'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
            </span>
            </th>
            <th @click="changeSort('actualStartAt')">
            실제 시작
            <span v-if="sortKey === 'actualStartAt'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
            </span>
            </th>
            <th @click="changeSort('actualEndAt')">
            실제 종료
            <span v-if="sortKey === 'actualEndAt'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
            </span>
            </th>
            <th @click="changeSort('targetQty')">
            목표 수량
            <span v-if="sortKey === 'targetQty'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
            </span>
            </th>
            <th @click="changeSort('currentGoodQty')">
            양품 수량
            <span v-if="sortKey === 'currentGoodQty'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
            </span>
            </th>
            <th @click="changeSort('status')">
            상태
            <span v-if="sortKey === 'status'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
            </span>
            </th>
            <th @click="changeSort('priority')">
            우선순위
            <span v-if="sortKey === 'priority'">
                {{ sortOrder === 'asc' ? '▲' : '▼' }}
            </span>
            </th>
            <th>수정</th>
            <th>삭제</th>
        </tr>
        </thead>
        <tbody>
        <tr v-for="wo in workOrders" :key="wo.workOrderId">
            <td>{{ wo.workOrderId }}</td>
            <td>{{ wo.lineId }}</td>
            <td>{{ wo.productId }}</td>
            <td>{{ wo.routingId }}</td>
            <td>{{ wo.plannedStartAt }}</td>
            <td>{{ wo.plannedEndAt }}</td>
            <td>{{ wo.actualStartAt }}</td>
            <td>{{ wo.actualEndAt }}</td>
            <td>{{ wo.targetQty }}</td>
            <td>{{ wo.currentGoodQty }}</td>
            <td>{{ wo.status }}</td>
            <td>{{ wo.priority }}</td>
            <td>
            <button @click="openEdit(wo)">수정</button>
            </td>
            <td>
            <button @click="remove(wo)">삭제</button>
            </td>
        </tr>
        </tbody>
    </table>

    <div v-if="!loading && workOrders.length === 0" class="empty">
        데이터가 없습니다.
    </div>
    <div v-if="loading">불러오는 중...</div>
    <div v-if="error" class="error">{{ error }}</div>

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
        <h2>{{ isEdit ? '작업지시 수정' : '작업지시 추가' }}</h2>
        <form @submit.prevent="submitForm">
        <div class="field">
            <label>라인 ID (lineId)</label>
            <input v-model="form.lineId" required />
        </div>

        <div class="field">
            <label>제품 ID (productId)</label>
            <input v-model="form.productId" required />
        </div>

        <div class="field">
            <label>라우팅 ID (routingId)</label>
            <input
            v-model="form.routingId"
            placeholder="예: R-P1000-A"
            required
            />
        </div>

        <div class="field">
            <label>계획 시작 (plannedStartAt)</label>
            <input
            v-model="form.plannedStartAt"
            placeholder="2026-02-19T16:00:00+09:00"
            required
            />
        </div>

        <div class="field">
            <label>계획 종료 (plannedEndAt)</label>
            <input
            v-model="form.plannedEndAt"
            placeholder="2026-02-20T00:00:00+09:00"
            required
            />
        </div>

        <div v-if="isEdit">
            <div class="field">
                <label>실제 시작 (actualStartAt)</label>
                <input
                v-model="form.actualStartAt"
                placeholder="2026-02-19T16:00:00+09:00"
                />
            </div>

            <div class="field">
                <label>실제 종료 (actualEndAt)</label>
                <input
                v-model="form.actualEndAt"
                placeholder="2026-02-20T00:00:00+09:00"
                />
            </div>
        </div>

        <div class="field">
            <label>목표 수량 (targetQty)</label>
            <input
            v-model.number="form.targetQty"
            type="number"
            min="1"
            required
            />
        </div>

        <div class="field">
            <label>우선순위 (priority)</label>
            <select v-model="form.priority">
            <option value="NORMAL">NORMAL</option>
            <option value="HIGH">HIGH</option>
            <option value="LOW">LOW</option>
            </select>
        </div>

        <div class="field" v-if="isEdit">
        <label>상태 (status)</label>
        <select v-model="form.status">
            <option value="PLANNED">PLANNED</option>
            <option value="RUNNING">RUNNING</option>
            <option value="CLOSED">CLOSED</option>
        </select>
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

const workOrders = ref([]);
const loading = ref(false);
const error = ref(null);

// 검색어 (서버에 전달)
const search = ref('');

// 정렬 상태 (서버에 sortBy / sortDir로 전달)
const sortKey = ref('plannedStartAt');
const sortOrder = ref('desc');

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
  productId: '',
  routingId: '',
  plannedStartAt: '',
  plannedEndAt: '',
  actualStartAt: '',
  actualEndAt: '',
  targetQty: 0,
  priority: 'NORMAL',
  status: 'PLANNED', // ← 추가
});

// 서버에서 작업지시 목록 가져오기 (페이지네이션/검색/정렬 포함)
async function fetchWorkOrders() {
  loading.value = true;
  error.value = null;
  try {
    const res = await api.get('/work-orders', {
      params: {
        page: currentPage.value,
        perPage: pageSize.value,
        sortBy: sortKey.value || 'plannedStartAt',
        sortDir: sortOrder.value || 'asc',
        // search를 서버에서 workOrderId 검색에 쓰도록 구현해두면 좋음
        search: search.value || null,
      },
    });

    workOrders.value = res.data.data || [];

    const meta = res.data.meta || {};
    currentPage.value = meta.page || 1;
    pageSize.value = meta.perPage || pageSize.value;
    totalPages.value = meta.totalPages || 1;
    totalCount.value = meta.total || workOrders.value.length;
  } catch (e) {
    console.error(e);
    error.value = e?.message || '작업지시 목록을 불러오지 못했습니다.';
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  currentPage.value = 1;
  fetchWorkOrders();
}

function onPageSizeChange() {
  currentPage.value = 1;
  fetchWorkOrders();
}

function changeSort(key) {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortOrder.value = 'asc';
  }
  currentPage.value = 1;
  fetchWorkOrders();
}

function goPrev() {
  if (currentPage.value === 1) return;
  currentPage.value -= 1;
  fetchWorkOrders();
}

function goNext() {
  if (currentPage.value === totalPages.value) return;
  currentPage.value += 1;
  fetchWorkOrders();
}

function openCreate() {
  isEdit.value = false;
  currentId.value = null;
  form.value = {
    lineId: '',
    productId: '',
    routingId: '',
    plannedStartAt: '',
    plannedEndAt: '',
    actualStartAt: '',
    actualEndAt: '',
    targetQty: 0,
    priority: 'NORMAL',
    status: 'PLANNED',
  };
  showModal.value = true;
}

function openEdit(wo) {
  isEdit.value = true;
  currentId.value = wo.workOrderId;
  form.value = {
    lineId: wo.lineId,
    productId: wo.productId,
    routingId: wo.routingId,
    plannedStartAt: wo.plannedStartAt,
    plannedEndAt: wo.plannedEndAt,
    actualStartAt: wo.actualStartAt || '',
    actualEndAt: wo.actualEndAt || '',
    targetQty: wo.targetQty,
    priority: wo.priority || 'NORMAL',
    status: wo.status || 'PLANNED', // ← 추가
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

async function submitForm() {
  if (form.value.targetQty <= 0) {
    alert('목표 수량은 1 이상이어야 합니다.');
    return;
  }

  try {
    if (isEdit.value && currentId.value) {
      // 수정: 백엔드 update가 요구하는 필드 모두 전달
        await api.put(`/work-orders/${currentId.value}`, {
        lineId: form.value.lineId,
        productId: form.value.productId,
        routingId: form.value.routingId,
        plannedStartAt: form.value.plannedStartAt,
        plannedEndAt: form.value.plannedEndAt,
        actualStartAt: form.value.actualStartAt || null,
        actualEndAt: form.value.actualEndAt || null,
        targetQty: form.value.targetQty,
        priority: form.value.priority,
        status: form.value.status,
        });
    } else {
      // 생성: 백엔드 store가 요구하는 필드 모두 전달
      await api.post('/work-orders', {
        lineId: form.value.lineId,
        productId: form.value.productId,
        routingId: form.value.routingId,
        plannedStartAt: form.value.plannedStartAt,
        plannedEndAt: form.value.plannedEndAt,
        targetQty: form.value.targetQty,
        priority: form.value.priority,
      });
    }
    showModal.value = false;
    await fetchWorkOrders();
  } catch (e) {
    console.error(e);
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error?.message ||
      '저장 중 오류가 발생했습니다.';
    alert(msg);
  }
}

async function remove(wo) {
  if (!confirm(`작업지시 ${wo.workOrderId} 를 삭제할까요?`)) return;
  try {
    await api.delete(`/work-orders/${wo.workOrderId}`);
    await fetchWorkOrders();
  } catch (e) {
    console.error(e);
    alert(e?.response?.data?.message || '삭제 중 오류가 발생했습니다.');
  }
}

// 서버 페이지네이션 기준 Export
async function exportExcel() {
  try {
    const res = await api.get('/work-orders/export', {
      params: {
        page: currentPage.value,
        perPage: pageSize.value,
        sortBy: sortKey.value || 'plannedStartAt',
        sortDir: sortOrder.value || 'desc',
        search: search.value || null,
      },
      responseType: 'blob',
    });

    const blob = new Blob([res.data]);
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `work-orders-page${currentPage.value}.xlsx`;
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
    const res = await api.post('/work-orders/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    alert(res.data?.message || 'Import 완료');
    await fetchWorkOrders();
  } catch (e) {
    console.error(e);
    alert(
      e?.response?.data?.message ||
        'Import 중 오류가 발생했습니다. 형식을 확인해주세요.',
    );
  } finally {
    event.target.value = '';
  }
}

onMounted(fetchWorkOrders);
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
  align-items: center;
  gap: 12px;
}
.filters input {
  padding: 4px 8px;
  width: 240px;
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
  width: 320px;
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
