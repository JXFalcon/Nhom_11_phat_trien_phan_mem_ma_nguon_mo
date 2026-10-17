const state = { products: [], customers: [], orders: [], dashboard: null, page: 'dashboard' };
const money = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 });
const shortDate = new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
const fullDate = new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
const icons = ['▤', '◈', '▧', '◇', '✳'];
let noticeTimer;
let modalType = '';
let editingId = null;

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[character]));
const formatMoney = (value) => money.format(Number(value || 0));
const formatDate = (value) => shortDate.format(new Date(value));
const productIcon = (index) => icons[index % icons.length];
const productTone = (index) => `tone-${index % 4}`;

async function api(resource, { method = 'GET', id = '', body } = {}) {
  const params = new URLSearchParams({ resource });
  if (id) params.set('id', id);
  const options = { method, headers: { Accept: 'application/json' } };
  if (body !== undefined) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }
  const response = await fetch(`api.php?${params}`, options);
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.error || 'Không thể hoàn thành yêu cầu.');
  return payload.data;
}

async function refreshAll() {
  const [dashboard, products, customers, orders] = await Promise.all([
    api('dashboard'), api('products'), api('customers'), api('orders'),
  ]);
  state.dashboard = dashboard;
  state.products = products;
  state.customers = customers;
  state.orders = orders;
  render();
}

function showNotice(message, isError = false) {
  const notice = $('#notice');
  notice.textContent = message;
  notice.classList.toggle('error', isError);
  notice.hidden = false;
  clearTimeout(noticeTimer);
  noticeTimer = setTimeout(() => { notice.hidden = true; }, 3600);
}

function setLoading(button, busy) {
  button.disabled = busy;
  button.dataset.originalText ||= button.textContent;
  button.textContent = busy ? 'Đang lưu...' : button.dataset.originalText;
  button.style.opacity = busy ? '.7' : '';
  button.style.cursor = busy ? 'wait' : '';
}

function render() {
  renderDashboard();
  renderProducts();
  renderCustomers();
  renderOrders();
  $('#product-nav-count').textContent = state.products.length;
}

function renderDashboard() {
  const data = state.dashboard;
  if (!data) return;
  $('#stat-revenue-today').textContent = formatMoney(data.revenue_today);
  $('#stat-orders').textContent = data.order_count.toLocaleString('vi-VN');
  $('#stat-products').textContent = data.product_count.toLocaleString('vi-VN');
  $('#stat-customers').textContent = data.customer_count.toLocaleString('vi-VN');
  $('#low-stock-count').textContent = data.low_stock_count;

  const stockList = $('#low-stock-list');
  stockList.innerHTML = data.low_stock.length
    ? data.low_stock.map((product, index) => `
      <div class="stock-row">
        <span class="product-thumb ${productTone(index)}">${productIcon(index)}</span>
        <span class="stock-copy"><strong>${escapeHtml(product.name)}</strong><small>${escapeHtml(product.category)}</small></span>
        <span class="stock-number">Còn ${product.stock}</span>
      </div>`).join('')
    : '<div class="empty-state">Tồn kho đang ổn định, chưa có mặt hàng sắp hết.</div>';
  renderChart(data.weekly_sales);
  const recent = $('#recent-orders-body');
  recent.innerHTML = data.recent_orders.length
    ? data.recent_orders.map(order => `
      <tr>
        <td><span class="order-id">#${escapeHtml(order.id)}</span></td>
        <td><strong>${escapeHtml(order.customer_name)}</strong></td>
        <td>${formatDate(order.created_at)}</td>
        <td><strong>${formatMoney(order.total)}</strong></td>
        <td>${statusBadge(order.status)}</td>
      </tr>`).join('')
    : '<tr><td class="empty-row" colspan="5">Chưa có giao dịch nào.</td></tr>';
}

function renderChart(days) {
  const container = $('#sales-chart');
  const width = 620;
  const height = 165;
  const pad = { top: 20, right: 18, bottom: 27, left: 10 };
  const graphWidth = width - pad.left - pad.right;
  const graphHeight = height - pad.top - pad.bottom;
  const max = Math.max(...days.map(day => day.revenue), 100000);
  const points = days.map((day, index) => ({
    x: pad.left + (graphWidth * index) / Math.max(days.length - 1, 1),
    y: pad.top + graphHeight - (day.revenue / max) * graphHeight,
    ...day,
  }));
  const line = points.map(point => `${point.x},${point.y}`).join(' ');
  const area = `${pad.left},${pad.top + graphHeight} ${line} ${pad.left + graphWidth},${pad.top + graphHeight}`;
  const grid = [0, 0.5, 1].map(ratio => {
    const y = pad.top + graphHeight * ratio;
    return `<line class="chart-grid-line" x1="${pad.left}" y1="${y}" x2="${width - pad.right}" y2="${y}"></line>`;
  }).join('');
  const labels = points.map((point, index) => {
    const emphasize = index === points.length - 1;
    const dayLabel = new Intl.DateTimeFormat('vi-VN', { weekday: 'short' }).format(new Date(`${point.date}T12:00:00`));
    const tooltipY = Math.max(point.y - 9, 11);
    const tooltip = point.revenue > 0
      ? `<text class="chart-tooltip" x="${point.x}" y="${tooltipY}" text-anchor="middle">${escapeHtml(formatCompact(point.revenue))}</text>`
      : '';
    return `${tooltip}<circle class="chart-point" cx="${point.x}" cy="${point.y}" r="${emphasize ? 4 : 2.5}"></circle>
      <text class="chart-label" x="${point.x}" y="${height - 4}" text-anchor="middle" ${emphasize ? 'font-weight="700" fill="#567b5b"' : ''}>${escapeHtml(dayLabel)}</text>`;
  }).join('');
  container.innerHTML = `<svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Doanh thu bảy ngày gần nhất">
    <defs><linearGradient id="areaFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#78a77e" stop-opacity=".2"></stop><stop offset="100%" stop-color="#78a77e" stop-opacity="0"></stop></linearGradient></defs>
    ${grid}<polygon class="chart-area" points="${area}"></polygon><polyline class="chart-line" points="${line}"></polyline>${labels}
  </svg>`;
}

function formatCompact(value) {
  if (value >= 1000000) return `${new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 }).format(value / 1000000)}tr`;
  if (value >= 1000) return `${new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(value / 1000)}k`;
  return formatMoney(value);
}

function statusBadge(status) {
  const cancelled = status === 'cancelled';
  return `<span class="status-badge${cancelled ? ' cancelled' : ''}">${cancelled ? 'Đã hủy' : 'Hoàn thành'}</span>`;
}

function renderProducts() {
  const query = $('#global-search').value.trim().toLocaleLowerCase('vi');
  const category = $('#category-filter').value;
  const products = state.products.filter(product =>
    (!query || `${product.name} ${product.sku} ${product.category}`.toLocaleLowerCase('vi').includes(query))
    && (!category || product.category === category));
  const categories = [...new Set(state.products.map(product => product.category))].sort();
  if (categories.join('|') !== [...$('#category-filter').options].slice(1).map(option => option.value).join('|')) {
    $('#category-filter').innerHTML = '<option value="">Tất cả danh mục</option>'
      + categories.map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
    if (categories.includes(category)) $('#category-filter').value = category;
  }
  $('#products-total').textContent = `${products.length} sản phẩm`;
  $('#products-empty').hidden = products.length > 0;
  $('#products-body').innerHTML = products.map(product => {
    const index = state.products.findIndex(item => item.id === product.id);
    const low = product.stock <= product.low_stock;
    return `<tr>
      <td><span class="product-name-cell"><span class="product-thumb ${productTone(index)}">${productIcon(index)}</span><span class="product-name-copy"><strong>${escapeHtml(product.name)}</strong><small>Hàng hóa</small></span></span></td>
      <td>${escapeHtml(product.sku)}</td><td>${escapeHtml(product.category)}</td>
      <td><strong>${formatMoney(product.price)}</strong></td>
      <td><span class="${low ? 'stock-low' : 'stock-good'}">${product.stock} sản phẩm</span></td>
      <td>${low ? '<span class="stock-number">Sắp hết</span>' : '<span class="status-badge">Còn hàng</span>'}</td>
      <td><span class="row-actions"><button class="row-action" data-action="edit-product" data-id="${escapeHtml(product.id)}" aria-label="Sửa sản phẩm">✎</button><button class="row-action delete" data-action="delete-product" data-id="${escapeHtml(product.id)}" aria-label="Xóa sản phẩm">×</button></span></td>
    </tr>`;
  }).join('');
}

function renderCustomers() {
  const query = $('#global-search').value.trim().toLocaleLowerCase('vi');
  const customers = state.customers.filter(customer =>
    `${customer.name} ${customer.phone} ${customer.email} ${customer.address}`.toLocaleLowerCase('vi').includes(query));
  $('#customers-total').textContent = `${customers.length} khách hàng`;
  $('#customers-empty').hidden = customers.length > 0;
  $('#customers-body').innerHTML = customers.map((customer, index) => `
    <tr>
      <td><span class="product-name-cell"><span class="product-thumb ${productTone(index)}">${escapeHtml(customer.name.trim().charAt(0).toLocaleUpperCase('vi'))}</span><span class="product-name-copy"><strong>${escapeHtml(customer.name)}</strong><small>Khách hàng</small></span></span></td>
      <td>${escapeHtml(customer.phone)}</td><td>${escapeHtml(customer.email || '—')}</td><td>${escapeHtml(customer.address || '—')}</td>
      <td><span class="row-actions"><button class="row-action" data-action="edit-customer" data-id="${escapeHtml(customer.id)}" aria-label="Sửa khách hàng">✎</button><button class="row-action delete" data-action="delete-customer" data-id="${escapeHtml(customer.id)}" aria-label="Xóa khách hàng">×</button></span></td>
    </tr>`).join('');
}

function renderOrders() {
  const query = $('#global-search').value.trim().toLocaleLowerCase('vi');
  const filter = $('#order-filter').value;
  const orders = state.orders.filter(order =>
    (!query || `${order.id} ${order.customer_name}`.toLocaleLowerCase('vi').includes(query))
    && (!filter || order.status === filter));
  $('#orders-total').textContent = `${orders.length} đơn hàng`;
  $('#orders-empty').hidden = orders.length > 0;
  $('#orders-body').innerHTML = orders.map(order => {
    const items = order.items.map(item => `${escapeHtml(item.name)} × ${item.quantity}`).join(', ');
    return `<tr>
      <td><span class="order-id">#${escapeHtml(order.id)}</span></td><td><strong>${escapeHtml(order.customer_name)}</strong></td>
      <td title="${escapeHtml(items)}">${escapeHtml(items.length > 42 ? `${items.slice(0, 42)}…` : items)}</td>
      <td>${formatDate(order.created_at)}</td><td><strong>${formatMoney(order.total)}</strong></td>
      <td>${statusBadge(order.status)}</td>
      <td><span class="row-actions">${order.status === 'completed'
        ? `<button class="row-action delete" data-action="cancel-order" data-id="${escapeHtml(order.id)}" aria-label="Hủy đơn">↶</button>`
        : `<button class="row-action" data-action="restore-order" data-id="${escapeHtml(order.id)}" aria-label="Khôi phục đơn">↻</button>`}</span></td>
    </tr>`;
  }).join('');
}

function setPage(page) {
  state.page = page;
  $$('.page-view').forEach(section => { section.hidden = section.id !== `page-${page}`; });
  $$('.nav-link').forEach(link => link.classList.toggle('active', link.dataset.page === page));
  $('#breadcrumb-current').textContent = ({ dashboard: 'Tổng quan', products: 'Sản phẩm', orders: 'Đơn hàng', customers: 'Khách hàng' })[page];
  $('#sidebar').classList.remove('open');
  render();
}

function field(label, name, value = '', options = {}) {
  const inputType = options.type || 'text';
  const required = options.required === false ? '' : ' required';
  const full = options.full ? ' full' : '';
  const placeholder = options.placeholder ? ` placeholder="${escapeHtml(options.placeholder)}"` : '';
  const hint = options.hint ? `<small class="field-hint">${escapeHtml(options.hint)}</small>` : '';
  let control;
  if (options.choices) {
    const choices = options.choices.map(([choiceValue, choiceLabel]) =>
      `<option value="${escapeHtml(choiceValue)}"${String(choiceValue) === String(value) ? ' selected' : ''}>${escapeHtml(choiceLabel)}</option>`).join('');
    control = `<select id="field-${name}" name="${name}"${required}>${choices}</select>`;
  } else {
    const numeric = inputType === 'number' ? ' min="0" step="1"' : '';
    const textValue = inputType === 'password' ? '' : ` value="${escapeHtml(value)}"`;
    control = `<input id="field-${name}" name="${name}" type="${inputType}"${textValue}${numeric}${placeholder}${required}>`;
  }
  return `<div class="field${full}"><label for="field-${name}">${escapeHtml(label)}</label>${control}${hint}</div>`;
}

function openModal(type, record = null) {
  modalType = type;
  editingId = record?.id || null;
  $('#form-error').hidden = true;
  $('#form-error').textContent = '';
  $('#modal-title').textContent = ({ product: record ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm mới', customer: record ? 'Chỉnh sửa khách hàng' : 'Thêm khách hàng mới', order: 'Tạo đơn hàng mới' })[type];
  $('#modal-submit').textContent = type === 'order' ? 'Xác nhận tạo đơn' : 'Lưu thông tin';
  const fields = $('#modal-fields');
  if (type === 'product') {
    fields.innerHTML = field('Tên sản phẩm', 'name', record?.name || '', { full: true, placeholder: 'Ví dụ: Bình giữ nhiệt' })
      + field('Mã SKU', 'sku', record?.sku || '', { placeholder: 'VD: GD-001' })
      + field('Danh mục', 'category', record?.category || '', { placeholder: 'Ví dụ: Gia dụng' })
      + field('Giá bán (₫)', 'price', record?.price ?? '', { type: 'number', hint: 'Nhập giá theo đồng Việt Nam' })
      + field('Số lượng tồn kho', 'stock', record?.stock ?? '', { type: 'number' })
      + field('Cảnh báo khi còn', 'low_stock', record?.low_stock ?? 5, { type: 'number', hint: 'Ngưỡng tồn kho thấp' });
  } else if (type === 'customer') {
    fields.innerHTML = field('Họ và tên', 'name', record?.name || '', { full: true, placeholder: 'Tên khách hàng' })
      + field('Số điện thoại', 'phone', record?.phone || '', { type: 'tel', placeholder: '09xxxxxxxx' })
      + field('Email', 'email', record?.email || '', { type: 'email', required: false, placeholder: 'email@example.com' })
      + field('Địa chỉ', 'address', record?.address || '', { full: true, required: false, placeholder: 'Địa chỉ giao hàng' });
  } else {
    fields.innerHTML = `<div class="field full"><label>Sản phẩm trong đơn</label>
      <div id="order-lines"></div><button class="add-line" type="button" id="add-order-line">＋ Thêm sản phẩm</button>
      <small class="field-hint" id="order-stock-hint">Tồn kho sẽ được cập nhật sau khi xác nhận đơn hàng.</small>
      </div>
      <div class="field full"><label for="field-customer_id">Khách hàng</label>
        <select id="field-customer_id" name="customer_id">
          <option value="">Khách lẻ</option>
          ${state.customers.map(customer => `<option value="${escapeHtml(customer.id)}">${escapeHtml(customer.name)} · ${escapeHtml(customer.phone)}</option>`).join('')}
        </select></div>
      <div class="field" id="walkin-name-field"><label for="field-customer_name">Tên khách lẻ</label><input id="field-customer_name" name="customer_name" placeholder="Không bắt buộc"></div>
      <div class="field"><label for="field-note">Ghi chú</label><input id="field-note" name="note" placeholder="Không bắt buộc"></div>
      <div class="field full"><div class="order-total"><span>Tổng cộng</span><strong id="order-total-value">0 ₫</strong></div></div>`;
    $('#add-order-line').addEventListener('click', () => addOrderLine());
    $('#order-lines').addEventListener('input', updateOrderTotal);
    $('#order-lines').addEventListener('change', updateOrderTotal);
    $('#order-lines').addEventListener('click', event => {
      const remove = event.target.closest('[data-remove-line]');
      if (!remove) return;
      remove.closest('.order-line').remove();
      updateOrderTotal();
    });
    $('#field-customer_id').addEventListener('change', () => {
      $('#walkin-name-field').hidden = Boolean($('#field-customer_id').value);
    });
    addOrderLine();
  }
  $('#modal-backdrop').hidden = false;
  document.body.style.overflow = 'hidden';
  const firstField = $('input, select', fields);
  if (firstField) setTimeout(() => firstField.focus(), 0);
}

function addOrderLine() {
  if (state.products.length === 0) {
    showNotice('Bạn cần thêm sản phẩm trước khi tạo đơn.', true);
    return;
  }
  const availableProducts = state.products;
  const row = document.createElement('div');
  row.className = 'order-line';
  row.innerHTML = `<select name="line_product" aria-label="Sản phẩm">${availableProducts.map((product, index) =>
    `<option value="${escapeHtml(product.id)}" data-price="${product.price}" data-stock="${product.stock}">${escapeHtml(product.name)} (${product.stock} sp)</option>`).join('')}</select>
    <input name="line_quantity" type="number" min="1" step="1" value="1" aria-label="Số lượng">
    <button class="remove-line" type="button" data-remove-line aria-label="Xóa sản phẩm khỏi đơn">×</button>`;
  $('#order-lines').append(row);
  updateOrderTotal();
}

function updateOrderTotal() {
  const total = $$('.order-line', $('#order-lines')).reduce((sum, row) => {
    const option = $('select', row).selectedOptions[0];
    const quantity = Number($('input', row).value);
    const available = Number(option.dataset.stock);
    $('input', row).max = available;
    return sum + Number(option.dataset.price) * Math.max(0, quantity || 0);
  }, 0);
  $('#order-total-value').textContent = formatMoney(total);
}

function closeModal() {
  $('#modal-backdrop').hidden = true;
  document.body.style.overflow = '';
  $('#modal-form').reset();
  $('#modal-fields').innerHTML = '';
  $('#modal-submit').disabled = false;
  $('#modal-submit').textContent = modalType === 'order' ? 'Xác nhận tạo đơn' : 'Lưu thông tin';
  $('#modal-submit').style.opacity = '';
  $('#modal-submit').style.cursor = '';
  delete $('#modal-submit').dataset.originalText;
  modalType = '';
  editingId = null;
}

async function deleteRecord(resource, id, label) {
  if (!window.confirm(`Bạn có chắc muốn xóa ${label} này không?`)) return;
  try {
    await api(resource, { method: 'DELETE', id });
    await refreshAll();
    showNotice(`Đã xóa ${label}.`);
  } catch (error) {
    showNotice(error.message, true);
  }
}

async function changeOrderStatus(id, status) {
  const action = status === 'cancelled' ? 'hủy đơn hàng này' : 'khôi phục đơn hàng này';
  if (!window.confirm(`Bạn có muốn ${action}? Tồn kho sẽ được cập nhật tương ứng.`)) return;
  try {
    await api('orders', { method: 'PATCH', id, body: { status } });
    await refreshAll();
    showNotice(status === 'cancelled' ? 'Đơn hàng đã được hủy và hoàn lại kho.' : 'Đơn hàng đã được khôi phục.');
  } catch (error) {
    showNotice(error.message, true);
  }
}

document.addEventListener('click', async (event) => {
  const link = event.target.closest('a[data-page]');
  if (link) {
    event.preventDefault();
    location.hash = link.dataset.page;
    return;
  }
  const actionButton = event.target.closest('[data-action]');
  if (!actionButton) return;
  const { action, id } = actionButton.dataset;
  if (action === 'new-order') openModal('order');
  if (action === 'add-product') openModal('product');
  if (action === 'add-customer') openModal('customer');
  if (action === 'edit-product') openModal('product', state.products.find(item => item.id === id));
  if (action === 'edit-customer') openModal('customer', state.customers.find(item => item.id === id));
  if (action === 'delete-product') await deleteRecord('products', id, 'sản phẩm');
  if (action === 'delete-customer') await deleteRecord('customers', id, 'khách hàng');
  if (action === 'cancel-order') await changeOrderStatus(id, 'cancelled');
  if (action === 'restore-order') await changeOrderStatus(id, 'completed');
});

window.addEventListener('hashchange', () => {
  const page = location.hash.slice(1);
  setPage(['dashboard', 'products', 'orders', 'customers'].includes(page) ? page : 'dashboard');
});

$('#modal-close').addEventListener('click', closeModal);
$('#modal-cancel').addEventListener('click', closeModal);
$('#modal-backdrop').addEventListener('click', event => {
  if (event.target === $('#modal-backdrop')) closeModal();
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && !$('#modal-backdrop').hidden) closeModal();
});
$('#mobile-menu').addEventListener('click', () => $('#sidebar').classList.toggle('open'));
$('#global-search').addEventListener('input', () => {
  renderProducts();
  renderCustomers();
  renderOrders();
});
$('#category-filter').addEventListener('change', renderProducts);
$('#order-filter').addEventListener('change', renderOrders);
$('#modal-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const submit = $('#modal-submit');
  const error = $('#form-error');
  error.hidden = true;
  const fields = new FormData(event.currentTarget);
  let resource;
  let body;
  if (modalType === 'product') {
    resource = 'products';
    body = {
      name: fields.get('name'), sku: fields.get('sku'), category: fields.get('category'),
      price: Number(fields.get('price')), stock: Number(fields.get('stock')),
      low_stock: Number(fields.get('low_stock')),
    };
  } else if (modalType === 'customer') {
    resource = 'customers';
    body = {
      name: fields.get('name'), phone: fields.get('phone'), email: fields.get('email'),
      address: fields.get('address'),
    };
  } else {
    resource = 'orders';
    body = {
      customer_id: fields.get('customer_id'), customer_name: fields.get('customer_name'),
      note: fields.get('note'),
      items: $$('.order-line', $('#order-lines')).map(row => ({
        product_id: $('select', row).value, quantity: Number($('input', row).value),
      })),
    };
  }

  const successMessage = modalType === 'order'
    ? 'Đơn hàng đã được tạo thành công.'
    : editingId ? 'Đã cập nhật thông tin.' : 'Đã thêm mới thành công.';
  setLoading(submit, true);
  try {
    await api(resource, { method: editingId ? 'PUT' : 'POST', id: editingId || '', body });
    closeModal();
    await refreshAll();
    showNotice(successMessage);
  } catch (requestError) {
    error.textContent = requestError.message;
    error.hidden = false;
  } finally {
    if (!$('#modal-backdrop').hidden) setLoading(submit, false);
  }
});

$('#today-label').textContent = fullDate.format(new Date());
const initialPage = location.hash.slice(1);
setPage(['dashboard', 'products', 'orders', 'customers'].includes(initialPage) ? initialPage : 'dashboard');
refreshAll().catch(error => showNotice(`Không thể tải dữ liệu: ${error.message}`, true));
