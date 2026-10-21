<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import api from './services/api'
import LoginView from './Login.vue'
import RegisterView from './Register.vue'
import ProductForm from './components/ProductForm.vue'

const products = ref([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const token = ref(localStorage.getItem('auth_token') || '')
const user = ref(null)
const authView = ref('')
const productDialog = ref(false)
const editingProduct = ref(null)
const pendingProductAction = ref(false)
const actionMessage = ref('')
const actionMessageType = ref('success')
const isAuthenticated = computed(() => Boolean(token.value))

const filteredProducts = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('id-ID')

  if (!query) return products.value

  return products.value.filter((product) =>
    [product.name, product.description]
      .filter(Boolean)
      .some((value) => value.toLocaleLowerCase('id-ID').includes(query)),
  )
})

const inventoryValue = computed(() =>
  products.value.reduce(
    (total, product) => total + Number(product.price) * Number(product.stock),
    0,
  ),
)

const lowStockCount = computed(
  () => products.value.filter((product) => Number(product.stock) <= 5).length,
)

function formatCurrency(value) {
  return Number(value).toLocaleString('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  })
}

function stockLabel(stock) {
  const quantity = Number(stock)
  if (quantity === 0) return 'Habis'
  if (quantity <= 5) return 'Menipis'
  return 'Tersedia'
}

async function fetchProducts() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/products')
    products.value = response.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal mengambil data produk.'
  } finally {
    loading.value = false
  }
}

function openRegistration() {
  authView.value = 'register'
}

function openLogin() {
  authView.value = 'login'
}

function handleAuthenticated(authData) {
  if (!authData?.token || !authData?.user) {
    actionMessage.value = 'Respons login tidak menyertakan token. Coba lagi.'
    actionMessageType.value = 'error'
    return
  }

  token.value = authData.token
  user.value = authData.user
  localStorage.setItem('auth_token', authData.token)
  localStorage.setItem('auth_user', JSON.stringify(authData.user))
  authView.value = ''
  actionMessage.value = 'Berhasil masuk. Fitur pengelolaan produk sudah aktif.'
  actionMessageType.value = 'success'

  if (pendingProductAction.value) {
    productDialog.value = true
    pendingProductAction.value = false
  }
}

function handleExpiredSession() {
  token.value = ''
  user.value = null
  authView.value = 'login'
  pendingProductAction.value = Boolean(productDialog.value)
  actionMessage.value = 'Sesi login tidak valid atau sudah berakhir. Silakan masuk kembali.'
  actionMessageType.value = 'error'
  localStorage.removeItem('auth_token')
  localStorage.removeItem('auth_user')
}

async function logout() {
  try {
    await api.post('/logout')
  } catch (err) {
    if (err.response?.status !== 401) {
      actionMessage.value =
        err.response?.data?.message || 'Sesi server tidak dapat ditutup. Token lokal dihapus.'
      actionMessageType.value = 'error'
    }
  } finally {
    token.value = ''
    user.value = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
    productDialog.value = false
    editingProduct.value = null
    actionMessage.value ||= 'Anda sudah keluar.'
    actionMessageType.value ||= 'success'
    await fetchProducts()
  }
}

function openCreateProduct() {
  actionMessage.value = ''
  if (!isAuthenticated.value) {
    pendingProductAction.value = true
    authView.value = 'login'
    return
  }

  editingProduct.value = null
  productDialog.value = true
}

function openEditProduct(product) {
  actionMessage.value = ''
  editingProduct.value = product
  productDialog.value = true
}

async function handleProductSaved() {
  productDialog.value = false
  editingProduct.value = null
  actionMessage.value = 'Data produk berhasil disimpan.'
  actionMessageType.value = 'success'
  await fetchProducts()
}

async function deleteProduct(product) {
  const confirmed = window.confirm(`Hapus produk "${product.name}"? Tindakan ini tidak dapat dibatalkan.`)
  if (!confirmed) return

  actionMessage.value = ''

  try {
    await api.delete(`/products/${product.id}`)
    actionMessage.value = `Produk "${product.name}" berhasil dihapus.`
    actionMessageType.value = 'success'
    await fetchProducts()
  } catch (err) {
    actionMessage.value =
      err.response?.data?.message || 'Produk gagal dihapus. Periksa sesi login dan coba lagi.'
    actionMessageType.value = 'error'
  }
}

onMounted(() => {
  window.addEventListener('auth:expired', handleExpiredSession)

  const storedUser = localStorage.getItem('auth_user')
  if (token.value && storedUser) {
    try {
      user.value = JSON.parse(storedUser)
    } catch {
      localStorage.removeItem('auth_user')
    }
  }

  if (token.value) {
    api.get('/user')
      .then((response) => {
        user.value = response.data.data
        localStorage.setItem('auth_user', JSON.stringify(response.data.data))
      })
      .catch((err) => {
        if (err.response?.status !== 401) {
          actionMessage.value =
            err.response?.data?.message || 'Sesi login gagal diverifikasi.'
          actionMessageType.value = 'error'
        }
      })
  }

  fetchProducts()
})

onBeforeUnmount(() => {
  window.removeEventListener('auth:expired', handleExpiredSession)
})
</script>

<template>
  <LoginView
    v-if="authView === 'login'"
    @authenticated="handleAuthenticated"
    @register="openRegistration"
    @back="authView = ''"
  />
  <RegisterView
    v-else-if="authView === 'register'"
    @authenticated="handleAuthenticated"
    @login="openLogin"
    @back="authView = ''"
  />
  <main v-else class="dashboard">
    <header class="topbar">
      <a class="brand" href="/" aria-label="Gudangku, beranda">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="m3.75 8.25 8.25-4.5 8.25 4.5v8.1L12 20.75l-8.25-4.4v-8.1Z" />
            <path d="m4.1 8.4 7.9 4.35 7.9-4.35M12 12.75v7.5M8 5.95l8.25 4.55" />
          </svg>
        </span>
        <span class="brand-name">gudang<span>ku</span></span>
      </a>

      <div class="topbar-right">
        <span class="system-status"><span></span> Sistem aktif</span>
        <template v-if="isAuthenticated">
          <span class="user-name">{{ user?.name || 'Pengguna' }}</span>
          <button class="account-button sign-out-button" type="button" @click="logout">Keluar</button>
        </template>
        <template v-else>
          <button class="account-button" type="button" @click="openLogin()">Masuk</button>
          <button class="account-button account-primary" type="button" @click="openRegistration()">Daftar</button>
        </template>
      </div>
    </header>

    <section class="welcome">
      <div class="welcome-copy">
        <p class="eyebrow">RINGKASAN INVENTORI</p>
        <h1>Semua produk,<br /><span>dalam kendali.</span></h1>
        <p class="welcome-text">
          Pantau persediaan dan nilai inventori tokomu dalam satu tempat.
        </p>
      </div>
      <div class="welcome-art" aria-hidden="true">
        <span class="art-orbit orbit-one"></span>
        <span class="art-orbit orbit-two"></span>
        <span class="art-box">
          <svg viewBox="0 0 120 120" fill="none">
            <path d="m19 42 41-22 41 22v42l-41 22-41-22V42Z" />
            <path d="m20 43 40 22 40-22M60 65v41M40 31l41 22v14" />
            <path d="m53 25 40 22v13" class="box-detail" />
          </svg>
        </span>
      </div>
      <span class="welcome-spark spark-one" aria-hidden="true">✳</span>
      <span class="welcome-spark spark-two" aria-hidden="true">✦</span>
    </section>

    <section class="summary-grid" aria-label="Ringkasan inventori">
      <article class="summary-card">
        <div class="summary-top">
          <span class="summary-icon icon-purple" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="m3.75 8.25 8.25-4.5 8.25 4.5v8.1L12 20.75l-8.25-4.4v-8.1Z" />
              <path d="m4 8.5 8 4.25 8-4.25M12 12.75v7.5" />
            </svg>
          </span>
          <span class="summary-caption">SELURUH KATALOG</span>
        </div>
        <p class="summary-value">{{ products.length }}</p>
        <p class="summary-label">Produk terdaftar</p>
      </article>

      <article class="summary-card">
        <div class="summary-top">
          <span class="summary-icon icon-green" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M4 19.25h16M6.5 16V11m5.5 5V5m5.5 11V8" />
              <path d="m5 8 5-3 5 3 5-4" />
            </svg>
          </span>
          <span class="summary-caption">NILAI PERSEDIAAN</span>
        </div>
        <p class="summary-value summary-currency">{{ formatCurrency(inventoryValue) }}</p>
        <p class="summary-label">Total nilai stok tersedia</p>
      </article>

      <article class="summary-card">
        <div class="summary-top">
          <span class="summary-icon icon-orange" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M12 3.75 21 19.5H3l9-15.75Z" />
              <path d="M12 9v4.5m0 3.25v.1" />
            </svg>
          </span>
          <span class="summary-caption">PERLU DIPANTAU</span>
        </div>
        <p class="summary-value">{{ lowStockCount }}</p>
        <p class="summary-label">Produk dengan stok 5 atau kurang</p>
      </article>
    </section>

    <section class="products-panel">
      <div class="panel-heading">
        <div>
          <p class="eyebrow panel-eyebrow">INVENTORI TOKO</p>
          <h2>Daftar produk</h2>
          <p class="panel-subtitle">Kelola dan pantau semua produk yang tersedia.</p>
        </div>
        <div class="panel-actions">
          <button v-if="isAuthenticated" class="add-button" type="button" @click="openCreateProduct">
            <span aria-hidden="true">+</span> Tambah produk
          </button>
          <button class="refresh-button" type="button" :disabled="loading" @click="fetchProducts">
            <svg :class="{ spinning: loading }" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M20 11a8 8 0 0 0-14.75-3L3 11m0-6v6h6m-5 2a8 8 0 0 0 14.75 3L21 13m0 6v-6h-6" />
            </svg>
            {{ loading ? 'Memuat...' : 'Muat ulang' }}
          </button>
        </div>
      </div>

      <p v-if="actionMessage" class="action-message" :class="`message-${actionMessageType}`" role="status">
        {{ actionMessage }}
      </p>

      <div class="toolbar">
        <label class="search-box">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="10.8" cy="10.8" r="6.8" />
            <path d="m16 16 4.25 4.25" />
          </svg>
          <input
            v-model="search"
            type="search"
            placeholder="Cari nama atau deskripsi produk..."
            aria-label="Cari produk"
          />
        </label>
        <span class="result-count">
          Menampilkan <strong>{{ filteredProducts.length }}</strong> dari
          <strong>{{ products.length }}</strong> produk
        </span>
      </div>

      <div v-if="loading" class="feedback-state">
        <span class="loader"></span>
        <p>Menyiapkan daftar produk...</p>
      </div>

      <div v-else-if="error" class="feedback-state error-state" role="alert">
        <span class="feedback-symbol">!</span>
        <h3>Data belum bisa dimuat</h3>
        <p>{{ error }}</p>
        <button class="retry-button" type="button" @click="fetchProducts">Coba lagi</button>
      </div>

      <div v-else-if="products.length === 0" class="feedback-state empty-state">
        <span class="empty-icon" aria-hidden="true">
          <svg viewBox="0 0 48 48" fill="none">
            <path d="m7 16 17-9 17 9v17l-17 9-17-9V16Z" />
            <path d="m7.5 16.5 16.5 9 16.5-9M24 25.5V42" />
          </svg>
        </span>
        <h3>Belum ada produk</h3>
        <p>Produk yang ditambahkan akan muncul di sini.</p>
      </div>

      <div v-else-if="filteredProducts.length === 0" class="feedback-state empty-state">
        <span class="empty-icon" aria-hidden="true">
          <svg viewBox="0 0 48 48" fill="none">
            <circle cx="21" cy="21" r="12" />
            <path d="m30 30 10 10" />
          </svg>
        </span>
        <h3>Produk tidak ditemukan</h3>
        <p>Coba kata kunci lain untuk menemukan produk.</p>
      </div>

      <div v-else class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Produk</th>
              <th>Deskripsi</th>
              <th>Harga</th>
              <th>Stok</th>
              <th>Status</th>
              <th v-if="isAuthenticated">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in filteredProducts" :key="product.id">
              <td>
                <div class="product-cell">
                  <span class="product-avatar">{{ product.name?.charAt(0)?.toUpperCase() || '?' }}</span>
                  <span class="product-title">{{ product.name }}</span>
                </div>
              </td>
              <td class="description-cell">{{ product.description || 'Tidak ada deskripsi' }}</td>
              <td class="price-cell">{{ formatCurrency(product.price) }}</td>
              <td class="stock-cell">{{ product.stock }} <span>unit</span></td>
              <td>
                <span class="stock-badge" :class="`stock-${Number(product.stock) === 0 ? 'empty' : Number(product.stock) <= 5 ? 'low' : 'good'}`">
                  <span></span>{{ stockLabel(product.stock) }}
                </span>
              </td>
              <td v-if="isAuthenticated">
                <div class="row-actions">
                  <button class="row-button edit-button" type="button" :aria-label="`Edit ${product.name}`" @click="openEditProduct(product)">Edit</button>
                  <button class="row-button delete-button" type="button" :aria-label="`Hapus ${product.name}`" @click="deleteProduct(product)">Hapus</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="!loading && !error && products.length > 0" class="panel-footer">
        <span>Data inventori diperbarui saat halaman dimuat ulang.</span>
        <span class="footer-mark"><span></span> Gudangku Inventory</span>
      </footer>
    </section>

    <footer class="page-footer">
      <span>© 2026 Gudangku</span>
      <span>Dibuat untuk pengelolaan inventori yang lebih mudah.</span>
    </footer>

    <ProductForm
      v-if="productDialog && isAuthenticated"
      :key="editingProduct?.id || 'new-product'"
      :product="editingProduct"
      @saved="handleProductSaved"
      @cancel="productDialog = false"
    />
  </main>
</template>

<style scoped>
.dashboard {
  --ink: #20233a;
  --muted: #85899e;
  --line: #ececf3;
  width: min(1160px, calc(100% - 56px));
  margin: 0 auto;
  padding: 0 0 34px;
}

.topbar {
  height: 82px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(232, 232, 241, 0.9);
}

.brand,
.topbar-right,
.system-status,
.summary-top,
.panel-heading,
.toolbar,
.product-cell,
.stock-badge,
.panel-footer,
.page-footer {
  display: flex;
  align-items: center;
}

.brand {
  gap: 11px;
  color: var(--ink);
  text-decoration: none;
}

.brand-mark {
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: 12px;
  background: #29283e;
  color: white;
}

.brand-mark svg {
  width: 23px;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.6;
}

.brand-name {
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -1px;
}

.brand-name span {
  color: #8d7cf6;
}

.topbar-right {
  gap: 20px;
}

.user-name {
  max-width: 150px;
  overflow: hidden;
  color: #57566c;
  font-size: 11px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-button {
  min-height: 35px;
  padding: 0 13px;
  border: 1px solid #e6e4f0;
  border-radius: 9px;
  background: #fff;
  color: #605b83;
  cursor: pointer;
  font: inherit;
  font-size: 11px;
  font-weight: 750;
  transition: 150ms ease;
}

.account-button:hover {
  border-color: #c9c1ff;
  background: #f8f6ff;
}

.account-primary {
  border-color: #7668d8;
  background: #7668d8;
  color: #fff;
}

.account-primary:hover {
  border-color: #6658c6;
  background: #6658c6;
  color: #fff;
}

.sign-out-button {
  color: #a24f56;
}

.system-status {
  gap: 8px;
  color: #74788d;
  font-size: 12px;
  font-weight: 600;
}

.system-status span,
.footer-mark span {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #42c995;
  box-shadow: 0 0 0 4px rgba(66, 201, 149, 0.12);
}

.avatar {
  display: grid;
  width: 35px;
  height: 35px;
  place-items: center;
  border: 1px solid #e7e4ff;
  border-radius: 50%;
  background: #f0edff;
  color: #7666d7;
  font-size: 13px;
  font-weight: 800;
}

.welcome {
  position: relative;
  display: flex;
  min-height: 248px;
  align-items: center;
  justify-content: space-between;
  overflow: hidden;
  margin-top: 30px;
  padding: 34px 54px;
  border-radius: 22px;
  background: linear-gradient(112deg, #28273d 0%, #37324f 58%, #4b4269 100%);
  color: #fff;
  box-shadow: 0 18px 40px rgba(49, 43, 76, 0.14);
}

.welcome-copy,
.welcome-art {
  position: relative;
  z-index: 1;
}

.eyebrow {
  margin: 0;
  color: #a99aff;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1.7px;
}

.welcome h1 {
  margin: 12px 0 10px;
  font-size: clamp(30px, 4vw, 43px);
  font-weight: 750;
  letter-spacing: -1.9px;
  line-height: 1.08;
}

.welcome h1 span {
  color: #bcb1ff;
}

.welcome-text {
  max-width: 430px;
  margin: 0;
  color: #c5c2d4;
  font-size: 13px;
  line-height: 1.7;
}

.welcome-art {
  display: grid;
  width: 230px;
  height: 190px;
  flex: 0 0 230px;
  place-items: center;
}

.art-orbit {
  position: absolute;
  width: 175px;
  height: 175px;
  border: 1px solid rgba(195, 183, 255, 0.18);
  border-radius: 50%;
}

.orbit-two {
  width: 220px;
  height: 220px;
}

.art-box {
  display: grid;
  width: 128px;
  height: 128px;
  place-items: center;
  transform: rotate(-7deg);
  border: 1px solid rgba(208, 198, 255, 0.28);
  border-radius: 33px;
  background: linear-gradient(145deg, rgba(183, 168, 255, 0.2), rgba(118, 105, 177, 0.08));
  box-shadow: 0 18px 40px rgba(19, 17, 37, 0.18);
  backdrop-filter: blur(8px);
}

.art-box svg {
  width: 88px;
  stroke: #d0c8ff;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2.2;
}

.art-box .box-detail {
  stroke: #a99aff;
}

.welcome-spark {
  position: absolute;
  color: #a99aff;
  opacity: 0.75;
}

.spark-one {
  top: 34px;
  right: 34%;
  font-size: 22px;
}

.spark-two {
  right: 48px;
  bottom: 30px;
  font-size: 20px;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 17px;
  margin: 20px 0 28px;
}

.summary-card {
  min-height: 145px;
  padding: 20px 22px;
  border: 1px solid #ecebf3;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 5px 18px rgba(47, 44, 75, 0.025);
}

.summary-top {
  gap: 11px;
}

.summary-icon {
  display: grid;
  width: 34px;
  height: 34px;
  place-items: center;
  border-radius: 11px;
}

.summary-icon svg {
  width: 19px;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.7;
}

.icon-purple {
  background: #f0edff;
  color: #8272e9;
}

.icon-green {
  background: #e7f8ef;
  color: #36ad79;
}

.icon-orange {
  background: #fff3e6;
  color: #ec9b3d;
}

.summary-caption {
  color: #8e91a3;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 1.1px;
}

.summary-value {
  margin: 14px 0 1px;
  color: #27263e;
  font-size: 29px;
  font-weight: 750;
  letter-spacing: -1px;
  line-height: 1.1;
}

.summary-currency {
  font-size: clamp(19px, 2.1vw, 25px);
  letter-spacing: -0.8px;
}

.summary-label {
  margin: 6px 0 0;
  color: #8b8ea1;
  font-size: 11px;
}

.products-panel {
  overflow: hidden;
  border: 1px solid #ecebf3;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 8px 28px rgba(47, 44, 75, 0.035);
}

.panel-heading {
  justify-content: space-between;
  gap: 20px;
  padding: 25px 28px 21px;
}

.panel-actions {
  display: flex;
  align-items: center;
  gap: 9px;
}

.add-button {
  display: inline-flex;
  min-height: 38px;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 0 13px;
  border: 1px solid #7668d8;
  border-radius: 10px;
  background: #7668d8;
  color: #fff;
  cursor: pointer;
  font: inherit;
  font-size: 11px;
  font-weight: 750;
  transition: 160ms ease;
}

.add-button:hover {
  border-color: #6658c6;
  background: #6658c6;
}

.add-button > span {
  font-size: 18px;
  font-weight: 400;
  line-height: 1;
}

.action-message {
  margin: 0;
  padding: 10px 28px;
  border-top: 1px solid #f0eff5;
  font-size: 11px;
}

.message-success {
  background: #f1fbf5;
  color: #258957;
}

.message-error {
  background: #fff4f3;
  color: #bc524d;
}

.panel-eyebrow {
  color: #8b7be8;
  font-size: 9px;
}

.panel-heading h2 {
  margin: 7px 0 4px;
  color: #28273d;
  font-size: 21px;
  font-weight: 750;
  letter-spacing: -0.5px;
}

.panel-subtitle {
  margin: 0;
  color: #8b8ea1;
  font-size: 11px;
}

.refresh-button,
.retry-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid #e8e6f3;
  border-radius: 10px;
  background: #fff;
  color: #615b8e;
  cursor: pointer;
  font: inherit;
  font-size: 11px;
  font-weight: 700;
  transition: 160ms ease;
}

.refresh-button:hover:not(:disabled),
.retry-button:hover {
  border-color: #c9c1ff;
  background: #f7f5ff;
  color: #6f5fe0;
}

.refresh-button:disabled {
  cursor: wait;
  opacity: 0.65;
}

.refresh-button svg {
  width: 15px;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.8;
}

.spinning {
  animation: spin 1s linear infinite;
}

.toolbar {
  min-height: 66px;
  justify-content: space-between;
  gap: 16px;
  padding: 11px 28px;
  border-top: 1px solid #f0eff5;
  border-bottom: 1px solid #f0eff5;
  background: #fdfdff;
}

.search-box {
  display: flex;
  width: min(340px, 60%);
  height: 37px;
  align-items: center;
  gap: 9px;
  padding: 0 11px;
  border: 1px solid #ecebf2;
  border-radius: 9px;
  background: #fff;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.search-box:focus-within {
  border-color: #b9adff;
  box-shadow: 0 0 0 3px rgba(139, 123, 232, 0.1);
}

.search-box svg {
  width: 16px;
  flex: 0 0 auto;
  stroke: #9a9bad;
  stroke-linecap: round;
  stroke-width: 1.8;
}

.search-box input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: #34334a;
  font: inherit;
  font-size: 11px;
}

.search-box input::placeholder {
  color: #a3a5b4;
}

.search-box input::-webkit-search-cancel-button {
  cursor: pointer;
}

.result-count {
  color: #9294a5;
  font-size: 10px;
}

.result-count strong {
  color: #56556d;
  font-weight: 750;
}

.table-scroll {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

th {
  height: 44px;
  padding: 0 18px;
  background: #fbfbfd;
  color: #9698a8;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  white-space: nowrap;
}

th:first-child,
td:first-child {
  padding-left: 28px;
}

th:last-child,
td:last-child {
  padding-right: 28px;
}

td {
  height: 67px;
  padding: 10px 18px;
  border-top: 1px solid #f0eff5;
  color: #57586c;
  font-size: 11px;
  white-space: nowrap;
}

tbody tr {
  transition: background-color 140ms ease;
}

tbody tr:hover {
  background: #fcfbff;
}

.product-cell {
  gap: 11px;
}

.product-avatar {
  display: grid;
  width: 35px;
  height: 35px;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 11px;
  background: linear-gradient(145deg, #f0edff, #e7e2ff);
  color: #7767d7;
  font-size: 13px;
  font-weight: 800;
}

.product-title {
  color: #36354c;
  font-size: 11px;
  font-weight: 750;
}

.description-cell {
  max-width: 260px;
  overflow: hidden;
  color: #8b8da0;
  text-overflow: ellipsis;
}

.price-cell {
  color: #45445a;
  font-weight: 700;
}

.stock-cell {
  color: #45445a;
  font-weight: 750;
}

.stock-cell span {
  color: #9a9bab;
  font-size: 10px;
  font-weight: 500;
}

.stock-badge {
  width: fit-content;
  gap: 7px;
  padding: 6px 9px;
  border-radius: 20px;
  font-size: 9px;
  font-weight: 750;
}

.stock-badge span {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.stock-good {
  background: #eaf8f0;
  color: #258957;
}

.stock-good span {
  background: #37b978;
}

.stock-low {
  background: #fff4e4;
  color: #b77821;
}

.stock-low span {
  background: #e6a33f;
}

.stock-empty {
  background: #fff0ef;
  color: #cf615b;
}

.stock-empty span {
  background: #e77970;
}

.row-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.row-button {
  min-height: 29px;
  padding: 0 9px;
  border: 1px solid transparent;
  border-radius: 7px;
  background: transparent;
  cursor: pointer;
  font: inherit;
  font-size: 10px;
  font-weight: 750;
  transition: 140ms ease;
}

.edit-button {
  border-color: #e8e5ff;
  color: #7163ce;
}

.edit-button:hover {
  background: #f3f0ff;
}

.delete-button {
  color: #bd6262;
}

.delete-button:hover {
  background: #fff0ef;
}

.feedback-state {
  display: flex;
  min-height: 230px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px 20px;
  color: #8c8ea0;
  text-align: center;
}

.feedback-state > p {
  margin: 10px 0 0;
  font-size: 11px;
}

.loader {
  width: 27px;
  height: 27px;
  border: 3px solid #ece9ff;
  border-top-color: #8878e8;
  border-radius: 50%;
  animation: spin 750ms linear infinite;
}

.feedback-state h3 {
  margin: 12px 0 0;
  color: #4a4960;
  font-size: 14px;
  font-weight: 750;
}

.empty-icon {
  display: grid;
  width: 57px;
  height: 57px;
  place-items: center;
  border-radius: 18px;
  background: #f2efff;
  color: #8a7be7;
}

.empty-icon svg {
  width: 34px;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.6;
}

.error-state .feedback-symbol {
  display: grid;
  width: 35px;
  height: 35px;
  place-items: center;
  border-radius: 50%;
  background: #fff0ef;
  color: #cf615b;
  font-size: 17px;
  font-weight: 800;
}

.retry-button {
  margin-top: 16px;
}

.panel-footer {
  min-height: 46px;
  justify-content: space-between;
  gap: 12px;
  padding: 0 28px;
  border-top: 1px solid #f0eff5;
  color: #a0a1af;
  font-size: 9px;
}

.footer-mark {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #7f8092;
  font-weight: 650;
}

.footer-mark span {
  width: 6px;
  height: 6px;
  box-shadow: none;
}

.page-footer {
  justify-content: space-between;
  gap: 12px;
  padding: 20px 2px 0;
  color: #a0a1af;
  font-size: 9px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 760px) {
  .dashboard {
    width: min(100% - 32px, 600px);
  }

  .welcome {
    min-height: 220px;
    padding: 30px;
  }

  .welcome-art {
    width: 160px;
    flex-basis: 160px;
  }

  .summary-grid {
    gap: 11px;
  }

  .summary-card {
    min-height: 136px;
    padding: 16px;
  }

  .summary-caption {
    font-size: 8px;
    letter-spacing: 0.6px;
  }

  .panel-heading,
  .toolbar {
    padding-right: 20px;
    padding-left: 20px;
  }
}

@media (max-width: 560px) {
  .dashboard {
    width: calc(100% - 28px);
  }

  .topbar {
    height: 68px;
  }

  .system-status {
    font-size: 10px;
  }

  .topbar-right {
    gap: 13px;
  }

  .topbar-right .system-status {
    display: none;
  }

  .account-button {
    min-height: 33px;
    padding: 0 9px;
    font-size: 10px;
  }

  .welcome {
    min-height: 215px;
    margin-top: 18px;
    padding: 25px 23px;
    border-radius: 18px;
  }

  .welcome h1 {
    font-size: 31px;
  }

  .welcome-text {
    max-width: 270px;
    font-size: 11px;
  }

  .welcome-art {
    position: absolute;
    right: -52px;
    bottom: -28px;
    width: 150px;
    height: 140px;
    opacity: 0.45;
  }

  .art-box {
    width: 94px;
    height: 94px;
  }

  .art-box svg {
    width: 66px;
  }

  .art-orbit {
    width: 130px;
    height: 130px;
  }

  .orbit-two {
    width: 165px;
    height: 165px;
  }

  .summary-grid {
    grid-template-columns: 1fr;
    gap: 10px;
    margin-top: 14px;
    margin-bottom: 20px;
  }

  .summary-card {
    min-height: 0;
    padding: 15px 17px;
  }

  .summary-card .summary-value {
    margin-top: 10px;
    font-size: 25px;
  }

  .summary-card .summary-currency {
    font-size: 22px;
  }

  .panel-heading {
    align-items: flex-start;
    padding: 20px 16px 17px;
  }

  .panel-heading h2 {
    font-size: 19px;
  }

  .panel-subtitle {
    max-width: 220px;
    font-size: 10px;
  }

  .refresh-button {
    min-height: 35px;
    gap: 6px;
    padding: 0 9px;
    font-size: 10px;
  }

  .panel-actions {
    flex-direction: column-reverse;
    align-items: stretch;
    gap: 6px;
  }

  .add-button {
    min-height: 35px;
    padding: 0 9px;
    font-size: 10px;
  }

  .action-message {
    padding: 10px 16px;
  }

  .toolbar {
    align-items: flex-start;
    flex-direction: column;
    gap: 9px;
    padding: 12px 16px;
  }

  .search-box {
    width: 100%;
  }

  .table-scroll table {
    min-width: 800px;
  }

  th:first-child,
  td:first-child {
    padding-left: 16px;
  }

  th:last-child,
  td:last-child {
    padding-right: 16px;
  }

  .panel-footer {
    align-items: flex-start;
    flex-direction: column;
    justify-content: center;
    padding: 10px 16px;
  }

  .page-footer {
    align-items: flex-start;
    flex-direction: column;
    padding-top: 16px;
  }
}
</style>
