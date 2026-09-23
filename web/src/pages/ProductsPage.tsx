import { useState } from "react"
import { keepPreviousData, useQuery } from "@tanstack/react-query"
import { api } from "@/lib/api"
import type { Connection, Paginated, Product } from "@/lib/types"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

const ALL = "all"

function money(v: string) {
  return new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(Number(v))
}

function priceRange(p: Product) {
  return p.price_min === p.price_max ? money(p.price_min) : `${money(p.price_min)} – ${money(p.price_max)}`
}

export default function ProductsPage() {
  const [search, setSearch] = useState("")
  const [connectionId, setConnectionId] = useState(ALL)
  const [page, setPage] = useState(1)

  const { data: connections } = useQuery({
    queryKey: ["connections"],
    queryFn: async () => (await api.get<{ data: Connection[] }>("/connections")).data.data,
  })

  const { data, isFetching } = useQuery({
    queryKey: ["products", { search, connectionId, page }],
    queryFn: async () => {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (search) params.search = search
      if (connectionId !== ALL) params.connection_id = connectionId
      return (await api.get<Paginated<Product>>("/products", { params })).data
    },
    placeholderData: keepPreviousData,
  })

  const products = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Products</h1>
        <p className="text-sm text-muted-foreground">
          Product catalog pulled from all connected Shopify stores.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Catalog</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap gap-3">
            <Input
              placeholder="Search title, vendor, type…"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1) }}
              className="max-w-xs"
            />
            <Select value={connectionId} onValueChange={(v) => { setConnectionId(v); setPage(1) }}>
              <SelectTrigger className="w-[220px]"><SelectValue placeholder="Store" /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>All stores</SelectItem>
                {connections?.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Product</TableHead>
                  <TableHead>Store</TableHead>
                  <TableHead>Vendor</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Variants</TableHead>
                  <TableHead className="text-right">Inventory</TableHead>
                  <TableHead className="text-right">Price</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {products.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={8} className="py-10 text-center text-muted-foreground">
                      {isFetching ? "Loading…" : "No products yet — sync a connected store."}
                    </TableCell>
                  </TableRow>
                )}
                {products.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell className="font-medium">{p.title}</TableCell>
                    <TableCell>{p.store.name}</TableCell>
                    <TableCell className="text-muted-foreground">{p.vendor}</TableCell>
                    <TableCell className="text-muted-foreground">{p.product_type}</TableCell>
                    <TableCell><Badge variant={p.status === "active" ? "default" : "secondary"}>{p.status}</Badge></TableCell>
                    <TableCell className="text-right">{p.variants_count}</TableCell>
                    <TableCell className="text-right">{p.total_inventory}</TableCell>
                    <TableCell className="text-right">{priceRange(p)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {meta && (
            <div className="flex items-center justify-between text-sm text-muted-foreground">
              <span>{meta.total} products · page {meta.current_page} of {meta.last_page}</span>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
                <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>Next</Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
