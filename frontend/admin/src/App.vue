<script setup>
import { ref } from 'vue'
const user = ref(null); const orders = ref([]); const error = ref(''); const form = ref({ email: '', password: '' })
async function api(path, body) { const r=await fetch(`/api/Admin/${path}`,{method:body?'POST':'GET',credentials:'include',headers:body?{'Content-Type':'application/json'}:{},body:body?JSON.stringify(body):undefined});const p=await r.json();if(!r.ok||!p.status)throw new Error(p.message);return p.data }
async function login(){try{user.value=await api('Auth/login',form.value);await loadOrders()}catch(e){error.value=e.message}}
async function loadOrders(){try{orders.value=(await api('Orders/index')).items}catch(e){error.value=e.message}}
async function update(order,status){try{await api(`Orders/update-status/${order.id}`,{status});order.status=status}catch(e){error.value=e.message}}
</script>
<template><main class="admin-shell"><p>VITA PICTURA</p><h1>{{ user ? 'Pesanan masuk' : 'Masuk admin' }}</h1><p v-if="error" class="error">{{ error }}</p><form v-if="!user" @submit.prevent="login"><input v-model="form.email" required type="email" placeholder="Email admin"><input v-model="form.password" required type="password" placeholder="Password"><button>Masuk</button></form><section v-else><button @click="loadOrders">Muat ulang</button><article v-for="order in orders" :key="order.id"><strong>{{ order.order_number }}</strong><span>{{ order.customer_name }} · {{ order.total }}</span><select :value="order.status" @change="update(order,$event.target.value)"><option value="processing">Processing</option><option value="shipped">Shipped</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></article></section></main></template>
