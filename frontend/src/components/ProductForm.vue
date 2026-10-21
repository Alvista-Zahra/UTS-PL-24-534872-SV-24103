<script setup>
import { reactive, ref } from 'vue'
import api from '../services/api'

const props = defineProps({
  product: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['saved', 'cancel'])
const loading = ref(false)
const error = ref('')
const form = reactive({
  name: props.product?.name || '',
  description: props.product?.description || '',
  price: props.product?.price ?? '',
  stock: props.product?.stock ?? '',
})

async function submitProduct() {
  loading.value = true
  error.value = ''

  const payload = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    price: Number(form.price),
    stock: Number(form.stock),
  }

  try {
    if (props.product) {
      await api.put(`/products/${props.product.id}`, payload)
    } else {
      await api.post('/products', payload)
    }
    emit('saved')
  } catch (err) {
    error.value =
      Object.values(err.response?.data?.errors || {}).flat().join(' ') ||
      err.response?.data?.message ||
      'Produk gagal disimpan. Coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('cancel')">
    <section class="product-modal" role="dialog" aria-modal="true" :aria-labelledby="product ? 'edit-product-title' : 'create-product-title'">
      <header class="modal-header">
        <div>
          <p class="eyebrow">{{ product ? 'PERBARUI INVENTORI' : 'TAMBAH KE INVENTORI' }}</p>
          <h2 :id="product ? 'edit-product-title' : 'create-product-title'">
            {{ product ? 'Edit produk' : 'Tambah produk' }}
          </h2>
        </div>
        <button class="close-button" type="button" aria-label="Tutup" @click="emit('cancel')">×</button>
      </header>

      <form class="product-form" @submit.prevent="submitProduct">
        <label>
          Nama produk
          <input v-model.trim="form.name" required maxlength="255" placeholder="Contoh: Headphone" />
        </label>
        <label>
          Deskripsi <span class="optional">(opsional)</span>
          <textarea v-model.trim="form.description" rows="3" placeholder="Ceritakan detail produk"></textarea>
        </label>
        <div class="form-row">
          <label>
            Harga (Rp)
            <input v-model="form.price" type="number" min="0" step="any" required placeholder="0" />
          </label>
          <label>
            Stok (unit)
            <input v-model="form.stock" type="number" min="0" step="1" required placeholder="0" />
          </label>
        </div>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        <footer class="modal-actions">
          <button class="cancel-button" type="button" :disabled="loading" @click="emit('cancel')">Batal</button>
          <button class="save-button" type="submit" :disabled="loading">
            {{ loading ? 'Menyimpan...' : product ? 'Simpan perubahan' : 'Simpan produk' }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.modal-backdrop { position: fixed; z-index: 20; inset: 0; display: grid; place-items: center; overflow-y: auto; padding: 20px; background: #17162999; backdrop-filter: blur(4px); }
.product-modal { width: min(100%, 500px); padding: 25px; border: 1px solid #ecebf3; border-radius: 18px; background: #fff; box-shadow: 0 24px 80px #17162940; }
.modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
.eyebrow { margin: 0 0 7px; color: #8878e8; font-size: 9px; font-weight: 800; letter-spacing: 1.4px; }
h2 { margin: 0; color: #29283e; font-size: 22px; letter-spacing: -.6px; }
.close-button { width: 32px; height: 32px; border: 0; border-radius: 9px; background: #f5f4fa; color: #77788c; cursor: pointer; font-size: 23px; line-height: 1; }
.product-form { display: grid; gap: 16px; }
label { display: grid; gap: 8px; color: #4e4d63; font-size: 11px; font-weight: 750; }
.optional { color: #a0a1af; font-weight: 500; }
input, textarea { width: 100%; border: 1px solid #e8e7ef; border-radius: 9px; outline: 0; background: #fff; color: #29283e; font: inherit; font-size: 12px; }
input { height: 42px; padding: 0 12px; }
textarea { resize: vertical; min-height: 76px; padding: 11px 12px; }
input:focus, textarea:focus { border-color: #aa9cff; box-shadow: 0 0 0 3px #8b7be81a; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-error { margin: 0; color: #c64f4f; font-size: 11px; line-height: 1.5; }
.modal-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 4px; }
.modal-actions button { min-height: 39px; padding: 0 14px; border-radius: 9px; cursor: pointer; font: inherit; font-size: 11px; font-weight: 750; }
.modal-actions button:disabled { cursor: wait; opacity: .65; }
.cancel-button { border: 1px solid #e8e7ef; background: #fff; color: #73758a; }
.save-button { border: 1px solid #7668d8; background: #7668d8; color: #fff; }
@media (max-width: 480px) { .product-modal { padding: 21px 17px; } .form-row { grid-template-columns: 1fr; } }
</style>
