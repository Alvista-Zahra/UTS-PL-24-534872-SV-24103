<script setup>
import { ref } from 'vue'
import api from './services/api'

const emit = defineEmits(['authenticated', 'register', 'back'])
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function submitLogin() {
  loading.value = true
  error.value = ''

  try {
    const response = await api.post('/login', {
      email: email.value,
      password: password.value,
    })
    emit('authenticated', response.data.data)
  } catch (err) {
    error.value =
      Object.values(err.response?.data?.errors || {}).flat().join(' ') ||
      err.response?.data?.message ||
      'Login gagal. Periksa kembali email dan password.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="auth-page">
    <a class="brand" href="/" aria-label="Gudangku">
      <span class="brand-mark">G</span>
      <span>gudang<span>ku</span></span>
    </a>
    <section class="auth-card">
      <p class="eyebrow">SELAMAT DATANG KEMBALI</p>
      <h1>Masuk ke akunmu</h1>
      <p class="subtitle">Kelola inventori dengan lebih mudah dan praktis.</p>

      <form @submit.prevent="submitLogin">
        <label>
          Email
          <input v-model.trim="email" type="email" autocomplete="email" required placeholder="nama@email.com" />
        </label>
        <label>
          Password
          <input v-model="password" type="password" autocomplete="current-password" required placeholder="Masukkan password" />
        </label>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        <button class="primary-button" type="submit" :disabled="loading">
          {{ loading ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>

      <p class="switch-copy">
        Belum punya akun?
        <button type="button" class="text-button" @click="emit('register')">Daftar sekarang</button>
      </p>
      <button type="button" class="back-button" @click="emit('back')">Kembali ke daftar produk</button>
    </section>
  </main>
</template>

<style scoped>
.auth-page { width: min(100% - 32px, 460px); min-height: 100vh; margin: auto; padding: 32px 0; }
.brand { display: inline-flex; align-items: center; gap: 10px; color: #29283e; text-decoration: none; font-size: 20px; font-weight: 800; letter-spacing: -1px; }
.brand > span:last-child > span { color: #8d7cf6; }
.brand-mark { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 12px; background: #29283e; color: #fff; font-size: 16px; }
.auth-card { margin-top: clamp(40px, 12vh, 100px); padding: 36px; border: 1px solid #ecebf3; border-radius: 20px; background: #fff; box-shadow: 0 16px 48px #302c5012; }
.eyebrow { margin: 0 0 9px; color: #8878e8; font-size: 10px; font-weight: 800; letter-spacing: 1.5px; }
h1 { margin: 0; color: #29283e; font-size: 28px; letter-spacing: -1px; }
.subtitle { margin: 9px 0 25px; color: #85899e; font-size: 13px; line-height: 1.6; }
form { display: grid; gap: 17px; }
label { display: grid; gap: 8px; color: #4e4d63; font-size: 12px; font-weight: 700; }
input { width: 100%; height: 44px; padding: 0 13px; border: 1px solid #e8e7ef; border-radius: 10px; outline: 0; color: #29283e; font-size: 13px; }
input:focus { border-color: #aa9cff; box-shadow: 0 0 0 3px #8b7be81a; }
.primary-button { height: 45px; margin-top: 2px; border: 0; border-radius: 10px; background: #7668d8; color: white; cursor: pointer; font-size: 13px; font-weight: 750; }
.primary-button:disabled { cursor: wait; opacity: .65; }
.form-error { margin: 0; color: #c64f4f; font-size: 12px; line-height: 1.5; }
.switch-copy { margin: 22px 0 0; color: #85899e; text-align: center; font-size: 12px; }
.text-button, .back-button { border: 0; background: none; color: #7565dc; cursor: pointer; font: inherit; font-weight: 750; }
.back-button { display: block; margin: 19px auto 0; color: #8a8ca0; font-size: 11px; }
@media (max-width: 480px) { .auth-card { padding: 27px 22px; } }
</style>
