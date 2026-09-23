export interface User {
  id: number
  name: string
  email: string
  roles: string[]
  permissions: string[]
}

export interface SalesOrder {
  id: number
  shopify_id: string
  order_number: string | null
  status: string | null
  financial_status: string | null
  fulfillment_status: string | null
  account_name: string | null
  customer_email: string | null
  currency: string
  total_amount: string
  shopify_created_at: string | null
  shopify_updated_at: string | null
  connection: { id: number; name?: string }
}

export interface Connection {
  id: number
  name: string
  platform: string
  shop_domain: string | null
  instance_url: string | null
  auth_type: string
  is_active: boolean
  sync_interval_seconds: number
  status: string
  payments_enabled: boolean
  last_synced_at: string | null
  last_error: string | null
  orders_count?: number
  payouts_count?: number
  created_at: string
}

export interface PayoutTransaction {
  id: number
  external_id: string
  type: string | null
  amount: string
  fee: string
  net: string
  source_order_external_id: string | null
  order?: { id: number; order_number: string | null } | null
}

export interface Payout {
  id: number
  external_id: string
  status: string | null
  issued_at: string | null
  currency: string
  amount: string
  gross: string
  fees: string
  refunds: string
  adjustments: string
  reserved: string
  connection: { id: number; name?: string }
  transactions_count?: number
  transactions?: PayoutTransaction[]
}

export interface Product {
  id: number
  shopify_id: string
  title: string | null
  vendor: string | null
  product_type: string | null
  status: string | null
  variants_count: number
  total_inventory: number
  price_min: string
  price_max: string
  image_url: string | null
  store: { id: number; name?: string }
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number; per_page: number }
}
