import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { api } from "@/lib/api"
import { useAuth } from "@/lib/auth"
import type { User } from "@/lib/types"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
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

const schema = z.object({
  name: z.string().min(1, "Name is required"),
  email: z.string().email(),
  password: z.string().min(8, "Min 8 characters"),
  role: z.string().min(1, "Pick a role"),
})
type FormValues = z.infer<typeof schema>

export default function UsersPage() {
  const { user: me } = useAuth()
  const qc = useQueryClient()

  const { data: users } = useQuery({
    queryKey: ["users"],
    queryFn: async () => (await api.get<{ data: User[] }>("/users")).data.data,
  })
  const { data: roles } = useQuery({
    queryKey: ["roles"],
    queryFn: async () => (await api.get<{ data: string[] }>("/roles")).data.data,
  })

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", email: "", password: "", role: "viewer" },
  })

  const createMutation = useMutation({
    mutationFn: (v: FormValues) => api.post("/users", v),
    onSuccess: () => {
      toast.success("User created")
      reset()
      qc.invalidateQueries({ queryKey: ["users"] })
    },
    onError: (e) => toast.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? "Could not create user"),
  })

  const roleMutation = useMutation({
    mutationFn: ({ id, role }: { id: number; role: string }) => api.put(`/users/${id}`, { role }),
    onSuccess: () => {
      toast.success("Role updated")
      qc.invalidateQueries({ queryKey: ["users"] })
    },
    onError: () => toast.error("Could not update role"),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/users/${id}`),
    onSuccess: () => {
      toast.success("User removed")
      qc.invalidateQueries({ queryKey: ["users"] })
    },
    onError: (e) => toast.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? "Delete failed"),
  })

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Users &amp; Roles</h1>
        <p className="text-sm text-muted-foreground">
          Manage who can access the consolidator. A role grants a set of permissions.
        </p>
      </div>

      <Card>
        <CardHeader><CardTitle>Users</CardTitle></CardHeader>
        <CardContent>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Role</TableHead>
                  <TableHead>Permissions</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {users?.map((u) => (
                  <TableRow key={u.id}>
                    <TableCell className="font-medium">
                      {u.name}{u.id === me?.id && <span className="ml-2 text-xs text-muted-foreground">(you)</span>}
                    </TableCell>
                    <TableCell className="text-muted-foreground">{u.email}</TableCell>
                    <TableCell>
                      <select
                        className="border-input h-8 rounded-md border bg-transparent px-2 text-sm"
                        value={u.roles[0] ?? ""}
                        onChange={(e) => roleMutation.mutate({ id: u.id, role: e.target.value })}
                      >
                        {roles?.map((r) => <option key={r} value={r}>{r}</option>)}
                      </select>
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        {u.permissions.map((p) => <Badge key={p} variant="secondary" className="text-xs">{p}</Badge>)}
                      </div>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button
                        size="sm"
                        variant="ghost"
                        className="text-destructive"
                        disabled={u.id === me?.id || deleteMutation.isPending}
                        onClick={() => { if (confirm(`Remove ${u.email}?`)) deleteMutation.mutate(u.id) }}
                      >
                        Delete
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card className="max-w-lg">
        <CardHeader><CardTitle>Add a user</CardTitle></CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit((v) => createMutation.mutate(v))} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="name">Name</Label>
                <Input id="name" {...register("name")} />
                {errors.name && <p className="text-sm text-destructive">{errors.name.message}</p>}
              </div>
              <div className="space-y-2">
                <Label htmlFor="role">Role</Label>
                <select id="role" className="border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs" {...register("role")}>
                  {roles?.map((r) => <option key={r} value={r}>{r}</option>)}
                </select>
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="email">Email</Label>
              <Input id="email" type="email" {...register("email")} />
              {errors.email && <p className="text-sm text-destructive">{errors.email.message}</p>}
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Temporary password</Label>
              <Input id="password" type="password" {...register("password")} />
              {errors.password && <p className="text-sm text-destructive">{errors.password.message}</p>}
            </div>
            <Button type="submit" disabled={isSubmitting || createMutation.isPending}>Create user</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
