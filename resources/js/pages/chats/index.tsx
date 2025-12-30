import AppLayout from '@/layouts/app-layout'
import { Head } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { useEffect, useRef, useState } from 'react'
import {
    Loader2, Send, CheckCircle2, AlertCircle, Edit3, Plus, Trash2,
    Search, Eye, Mic, MicOff, Code2, FileCode, FolderOpen, Save,
    Settings, ChevronDown, ChevronRight
} from 'lucide-react'
import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { Prism as SyntaxHighlighter } from 'react-syntax-highlighter'
import { vscDarkPlus } from 'react-syntax-highlighter/dist/esm/styles/prism'

type Message = {
    role: 'user' | 'ai'
    content: string
    toolResults?: any[]
}

type GeminiHistoryItem = {
    role: 'user' | 'model' | 'function'
    parts: any[]
}

export default function OrderChat() {
    const [message, setMessage] = useState('')
    const [messages, setMessages] = useState<Message[]>([])
    const [history, setHistory] = useState<GeminiHistoryItem[]>([])
    const [loading, setLoading] = useState(false)
    const [isListening, setIsListening] = useState(false)
    const [isRecognitionSupported, setIsRecognitionSupported] = useState(true)
    const [expandedCode, setExpandedCode] = useState<Record<number, boolean>>({})
    const bottomRef = useRef<HTMLDivElement>(null)
    const recognitionRef = useRef<any>(null)

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
    }, [messages])

    // Initialize Speech Recognition
    useEffect(() => {
        if (typeof window !== 'undefined') {
            const SpeechRecognition = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition

            if (SpeechRecognition) {
                const recognition = new SpeechRecognition()
                recognition.continuous = false
                recognition.interimResults = true
                recognition.lang = 'en-US'

                recognition.onstart = () => setIsListening(true)
                recognition.onresult = (event: any) => {
                    const transcript = Array.from(event.results)
                        .map((result: any) => result[0])
                        .map((result: any) => result.transcript)
                        .join('')
                    setMessage(transcript)
                }
                recognition.onerror = (event: any) => {
                    console.error('Speech recognition error:', event.error)
                    setIsListening(false)
                    if (event.error === 'not-allowed') {
                        alert('Microphone access denied. Please allow microphone access in your browser settings.')
                    }
                }
                recognition.onend = () => setIsListening(false)

                recognitionRef.current = recognition
            } else {
                setIsRecognitionSupported(false)
            }
        }

        return () => {
            if (recognitionRef.current) {
                recognitionRef.current.stop()
            }
        }
    }, [])

    const toggleListening = () => {
        if (!recognitionRef.current) return
        if (isListening) {
            recognitionRef.current.stop()
        } else {
            try {
                recognitionRef.current.start()
            } catch (error) {
                console.error('Failed to start speech recognition:', error)
            }
        }
    }

    const sendMessage = async () => {
        if (!message.trim() || loading) return
        if (isListening && recognitionRef.current) {
            recognitionRef.current.stop()
        }

        const userMessage = message.trim()
        setMessages(prev => [...prev, { role: 'user', content: userMessage }])
        setMessage('')
        setLoading(true)

        try {
            const response = await fetch('/chats/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement).content,
                },
                body: JSON.stringify({ message: userMessage, history: history }),
            })

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}))
                throw new Error(errorData.message || 'Request failed')
            }

            const data = await response.json()
            if (data.history) setHistory(data.history)
            setMessages(prev => [...prev, {
                role: 'ai',
                content: data.reply || 'No response received.',
                toolResults: data.toolResults,
            }])
        } catch (error) {
            console.error('Chat error:', error)
            setMessages(prev => [...prev, {
                role: 'ai',
                content: `Error: ${error instanceof Error ? error.message : 'Failed to get response. Please try again.'}`,
            }])
        } finally {
            setLoading(false)
        }
    }

    const toggleCodeExpansion = (index: number) => {
        setExpandedCode(prev => ({ ...prev, [index]: !prev[index] }))
    }

    const getFileLanguage = (filePath: string): string => {
        const ext = filePath.split('.').pop()?.toLowerCase()
        const langMap: Record<string, string> = {
            'php': 'php',
            'js': 'javascript',
            'jsx': 'jsx',
            'ts': 'typescript',
            'tsx': 'tsx',
            'json': 'json',
            'html': 'html',
            'css': 'css',
            'md': 'markdown',
        }
        return langMap[ext || ''] || 'text'
    }

    const renderToolResults = (toolResults?: any[]) => {
        if (!toolResults || toolResults.length === 0) return null

        return (
            <div className="mt-3 space-y-2">
                {toolResults.map((result, idx) => {
                    const response = result.functionResponse?.response
                    const hasError = response?.error
                    const toolName = result.functionResponse?.name
                    const isUpdate = toolName === 'update_order'
                    const isCreate = toolName === 'create_order'
                    const isDelete = toolName === 'delete_order'
                    const isView = toolName === 'view_order'
                    const isReadFile = toolName === 'read_file'
                    const isListFiles = toolName === 'list_files'
                    const isWriteFile = toolName === 'write_file'
                    const isAnalyzeCode = toolName === 'analyze_code'
                    const isCodeTool = isReadFile || isListFiles || isWriteFile || isAnalyzeCode

                    return (
                        <div
                            key={idx}
                            className={`rounded-md border p-3 text-xs ${hasError ? 'border-destructive/50 bg-destructive/5' :
                                isDelete ? 'border-red-500/50 bg-red-500/5' :
                                    isView ? 'border-blue-500/50 bg-blue-500/5' :
                                        isCodeTool ? 'border-purple-500/50 bg-purple-500/5' :
                                            'border-green-500/50 bg-green-500/5'
                                }`}
                        >
                            {/* Tool Header */}
                            <div className="flex items-center gap-2 mb-2">
                                {hasError ? <AlertCircle className="h-4 w-4 text-destructive" /> :
                                    isDelete ? <Trash2 className="h-4 w-4 text-red-600" /> :
                                        isView ? (response?.type === 'single' ? <Eye className="h-4 w-4 text-blue-600" /> : <Search className="h-4 w-4 text-blue-600" />) :
                                            isReadFile ? <FileCode className="h-4 w-4 text-purple-600" /> :
                                                isListFiles ? <FolderOpen className="h-4 w-4 text-purple-600" /> :
                                                    isWriteFile ? <Save className="h-4 w-4 text-purple-600" /> :
                                                        isAnalyzeCode ? <Settings className="h-4 w-4 text-purple-600" /> :
                                                            <CheckCircle2 className="h-4 w-4 text-green-600" />
                                }
                                <Badge variant="outline" className="text-xs flex items-center gap-1">
                                    {isCreate && <Plus className="h-3 w-3" />}
                                    {isUpdate && <Edit3 className="h-3 w-3" />}
                                    {isDelete && <Trash2 className="h-3 w-3" />}
                                    {isView && (response?.type === 'single' ? <Eye className="h-3 w-3" /> : <Search className="h-3 w-3" />)}
                                    {isReadFile && <FileCode className="h-3 w-3" />}
                                    {isListFiles && <FolderOpen className="h-3 w-3" />}
                                    {isWriteFile && <Save className="h-3 w-3" />}
                                    {isAnalyzeCode && <Code2 className="h-3 w-3" />}
                                    {isCreate ? 'Create Order' :
                                        isUpdate ? 'Update Order' :
                                            isDelete ? 'Delete Order' :
                                                isView ? (response?.type === 'single' ? 'View Order' : 'Search Orders') :
                                                    isReadFile ? 'Read File' :
                                                        isListFiles ? 'List Files' :
                                                            isWriteFile ? 'Write File' :
                                                                isAnalyzeCode ? 'Analyze Code' :
                                                                    toolName || 'Tool'}
                                </Badge>
                                {isView && response?.count && (
                                    <Badge variant="secondary" className="text-xs ml-auto">
                                        {response.count} {response.count === 1 ? 'result' : 'results'}
                                    </Badge>
                                )}
                            </div>

                            {hasError ? (
                                <p className="text-destructive">{response.error}</p>
                            ) : (
                                <div className="space-y-2 text-muted-foreground">
                                    {response?.message && (
                                        <p className={`font-medium ${isDelete ? 'text-red-600' : isCodeTool ? 'text-purple-600' : 'text-foreground'}`}>
                                            {response.message}
                                        </p>
                                    )}

                                    {/* Read File Tool Result */}
                                    {isReadFile && response?.content && (
                                        <div className="mt-2 space-y-2">
                                            <div className="flex items-center justify-between text-xs bg-background/80 rounded p-2">
                                                <div className="flex items-center gap-2">
                                                    <FileCode className="h-3 w-3" />
                                                    <span className="font-mono text-foreground">{response.file_path}</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-muted-foreground">
                                                    <span>{response.file_info?.lines} lines</span>
                                                    <span>•</span>
                                                    <span>{(response.file_info?.size / 1024).toFixed(1)} KB</span>
                                                </div>
                                            </div>
                                            <div className="relative">
                                                <button
                                                    onClick={() => toggleCodeExpansion(idx)}
                                                    className="flex items-center gap-1 text-xs text-purple-600 hover:text-purple-700 mb-1"
                                                >
                                                    {expandedCode[idx] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                                                    {expandedCode[idx] ? 'Collapse code' : 'Expand code'}
                                                </button>
                                                {expandedCode[idx] && (
                                                    <div className="rounded overflow-hidden max-h-[500px] overflow-y-auto">
                                                        <SyntaxHighlighter
                                                            language={getFileLanguage(response.file_path)}
                                                            style={vscDarkPlus}
                                                            customStyle={{ margin: 0, fontSize: '11px' }}
                                                            showLineNumbers
                                                        >
                                                            {response.content}
                                                        </SyntaxHighlighter>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* List Files Tool Result */}
                                    {isListFiles && (response?.files || response?.directories) && (
                                        <div className="mt-2 space-y-2">
                                            <div className="text-xs bg-background/80 rounded p-2">
                                                <span className="font-medium text-foreground">Directory: </span>
                                                <span className="font-mono">{response.directory}</span>
                                            </div>
                                            {response.directories && response.directories.length > 0 && (
                                                <div>
                                                    <p className="text-xs font-medium mb-1 text-foreground flex items-center gap-1">
                                                        <FolderOpen className="h-3 w-3" />
                                                        Directories ({response.total_directories})
                                                    </p>
                                                    <div className="space-y-1">
                                                        {response.directories.slice(0, 10).map((dir: any, i: number) => (
                                                            <div key={i} className="text-xs font-mono bg-background/50 rounded px-2 py-1">
                                                                📁 {dir.name}
                                                            </div>
                                                        ))}
                                                        {response.directories.length > 10 && (
                                                            <p className="text-xs text-muted-foreground">
                                                                ... and {response.directories.length - 10} more
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                            {response.files && response.files.length > 0 && (
                                                <div>
                                                    <p className="text-xs font-medium mb-1 text-foreground flex items-center gap-1">
                                                        <FileCode className="h-3 w-3" />
                                                        Files ({response.total_files})
                                                    </p>
                                                    <div className="space-y-1 max-h-[300px] overflow-y-auto">
                                                        {response.files.map((file: any, i: number) => (
                                                            <div key={i} className="flex items-center justify-between text-xs font-mono bg-background/50 rounded px-2 py-1">
                                                                <span>📄 {file.name}</span>
                                                                <span className="text-muted-foreground text-xs">
                                                                    {(file.size / 1024).toFixed(1)} KB
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {/* Write File Tool Result */}
                                    {isWriteFile && response?.action && (
                                        <div className="mt-2 space-y-2">
                                            <div className="bg-purple-50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-900 rounded p-2">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <Save className="h-4 w-4 text-purple-600" />
                                                    <span className="font-medium text-foreground">
                                                        File {response.action === 'created' ? 'Created' : 'Updated'}
                                                    </span>
                                                </div>
                                                <p className="text-xs font-mono text-muted-foreground">{response.file_path}</p>
                                                <div className="mt-2 flex items-center gap-3 text-xs text-muted-foreground">
                                                    <span>{response.lines} lines</span>
                                                    <span>•</span>
                                                    <span>{(response.file_size / 1024).toFixed(1)} KB</span>
                                                </div>
                                                {response.backup_created && (
                                                    <div className="mt-2 pt-2 border-t border-purple-200 dark:border-purple-900">
                                                        <p className="text-xs text-muted-foreground flex items-center gap-1">
                                                            ✓ Backup created: <span className="font-mono">{response.backup_path}</span>
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Analyze Code Tool Result */}
                                    {isAnalyzeCode && response?.results && (
                                        <div className="mt-2 space-y-2">
                                            <div className="text-xs bg-background/80 rounded p-2">
                                                <span className="font-medium text-foreground">Search: </span>
                                                <span className="font-mono">{response.search_term}</span>
                                                <span className="text-muted-foreground"> ({response.search_type})</span>
                                            </div>
                                            {response.results.length > 0 ? (
                                                <div className="space-y-1 max-h-[300px] overflow-y-auto">
                                                    {response.results.map((result: any, i: number) => (
                                                        <div key={i} className="bg-background/50 rounded p-2">
                                                            <p className="text-xs font-mono text-foreground mb-1">
                                                                📄 {result.file}
                                                            </p>
                                                            <div className="pl-4 space-y-0.5">
                                                                {result.matches.map((match: string, j: number) => (
                                                                    <p key={j} className="text-xs text-muted-foreground">
                                                                        • {match}
                                                                    </p>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="text-xs text-muted-foreground">No matches found</p>
                                            )}
                                        </div>
                                    )}

                                    {/* Warning for deletions */}
                                    {isDelete && response?.warning && (
                                        <div className="flex items-start gap-2 p-2 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900 rounded">
                                            <AlertCircle className="h-4 w-4 text-red-600 mt-0.5 flex-shrink-0" />
                                            <p className="text-xs text-red-700 dark:text-red-400 font-medium">
                                                {response.warning}
                                            </p>
                                        </div>
                                    )}

                                    {/* Order details (existing code) */}
                                    {response?.order && (
                                        <div className={`mt-2 rounded border p-2 space-y-1 ${isDelete ? 'border-red-200 dark:border-red-900 bg-red-50/50 dark:bg-red-950/10'
                                            : 'border-border/50 bg-background/50'
                                            }`}>
                                            <div className="flex items-center justify-between">
                                                <span className="font-mono font-semibold text-foreground">
                                                    #{response.order.order_no}
                                                </span>
                                                {response.order.status && !isDelete && (
                                                    <Badge variant={
                                                        response.order.status === 'Delivered' ? 'default' :
                                                            response.order.status === 'Cancelled' ? 'destructive' : 'secondary'
                                                    } className="text-xs">
                                                        {response.order.status}
                                                    </Badge>
                                                )}
                                                {isDelete && <Badge variant="destructive" className="text-xs">DELETED</Badge>}
                                            </div>
                                            <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                                <div>
                                                    <span className="text-muted-foreground">Client:</span>{' '}
                                                    <span className={`font-medium ${isDelete ? 'line-through text-muted-foreground' : 'text-foreground'}`}>
                                                        {response.order.client_name}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-muted-foreground">Amount:</span>{' '}
                                                    <span className={`font-medium ${isDelete ? 'line-through text-muted-foreground' : 'text-foreground'}`}>
                                                        {response.order.amount?.toLocaleString()} KES
                                                    </span>
                                                </div>
                                                <div className="col-span-2">
                                                    <span className="text-muted-foreground">Product:</span>{' '}
                                                    <span className={`font-medium ${isDelete ? 'line-through text-muted-foreground' : 'text-foreground'}`}>
                                                        {response.order.product_name} (×{response.order.quantity})
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Multiple orders (existing code) */}
                                    {isView && response?.type === 'multiple' && response?.orders && (
                                        <div className="mt-3 space-y-2">
                                            {response.filters && response.filters !== 'none' && (
                                                <div className="text-xs text-muted-foreground bg-background/50 rounded p-2 mb-2">
                                                    <span className="font-medium">Filters:</span> {response.filters}
                                                </div>
                                            )}
                                            <div className="space-y-2 max-h-[400px] overflow-y-auto">
                                                {response.orders.map((order: any, idx: number) => (
                                                    <div key={idx} className="rounded border border-border/50 bg-background/50 p-2 hover:bg-accent/5 transition-colors">
                                                        <div className="flex items-start justify-between gap-2 mb-1">
                                                            <span className="font-mono text-xs font-semibold text-foreground">#{order.order_no}</span>
                                                            <Badge variant={
                                                                order.status === 'Delivered' ? 'default' :
                                                                    order.status === 'Cancelled' ? 'destructive' : 'secondary'
                                                            } className="text-xs">
                                                                {order.status}
                                                            </Badge>
                                                        </div>
                                                        <div className="grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs">
                                                            <div>
                                                                <span className="text-muted-foreground">Client:</span>{' '}
                                                                <span className="text-foreground font-medium">{order.client_name}</span>
                                                            </div>
                                                            <div>
                                                                <span className="text-muted-foreground">Amount:</span>{' '}
                                                                <span className="text-foreground font-medium">{order.amount?.toLocaleString()} KES</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            {response.total_amount && (
                                                <div className="mt-2 pt-2 border-t border-border/50 text-xs">
                                                    <div className="flex justify-between items-center">
                                                        <span className="text-muted-foreground">Total Amount:</span>
                                                        <span className="font-semibold text-foreground">{response.total_amount.toLocaleString()} KES</span>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {/* Changes for updates (existing code) */}
                                    {isUpdate && response?.changes && response.changes.length > 0 && (
                                        <div className="mt-3 rounded border border-amber-500/30 bg-amber-500/5 p-2">
                                            <p className="font-medium text-foreground mb-2 text-xs flex items-center gap-1">
                                                <Edit3 className="h-3 w-3" />
                                                Changes Made ({response.changes_count}):
                                            </p>
                                            <div className="space-y-1.5">
                                                {response.changes.map((change: any, i: number) => (
                                                    <div key={i} className="flex items-center gap-2 text-xs bg-background/50 rounded p-1.5">
                                                        <span className="font-medium text-foreground capitalize min-w-[100px]">
                                                            {change.field.replace(/_/g, ' ')}:
                                                        </span>
                                                        <div className="flex items-center gap-1.5 flex-1">
                                                            <span className="line-through text-muted-foreground/70 text-xs">
                                                                {change.old_value || '(empty)'}
                                                            </span>
                                                            <span className="text-muted-foreground">→</span>
                                                            <span className="text-green-600 font-medium">
                                                                {change.new_value || '(empty)'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )
                })}
            </div>
        )
    }

    const quickActions = [
        { label: 'Create Order', prompt: 'Create a new order for', icon: Plus },
        { label: 'Search Orders', prompt: 'Show me orders', icon: Search },
        { label: 'View Code', prompt: 'Show me the code for', icon: Code2 },
        { label: 'List Files', prompt: 'List files in', icon: FolderOpen },
    ]

    const handleQuickAction = (prompt: string) => {
        setMessage(prompt + ' ')
    }

    return (
        <AppLayout>
            <Head title="AI Order Chat" />

            <div className="flex h-full flex-col rounded-xl border border-sidebar-border bg-background p-4">
                <div className="mb-4 border-b pb-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-semibold flex items-center gap-2">
                                🤖 AI Order Assistant
                                <Badge variant="outline" className="text-xs font-normal">
                                    Self-Improving
                                </Badge>
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Manage orders and code using natural language or voice
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            {!isRecognitionSupported && (
                                <Badge variant="outline" className="text-xs text-amber-600">
                                    Voice unavailable
                                </Badge>
                            )}
                            <Badge variant="outline" className="text-xs">
                                Powered by A.T.L.A.S
                            </Badge>
                        </div>
                    </div>
                </div>

                <ScrollArea className="flex-1 pr-4">
                    <div className="space-y-4 pb-4">
                        {messages.length === 0 && (
                            <div className="flex h-[400px] items-center justify-center">
                                <div className="text-center space-y-4 max-w-2xl">
                                    <div className="text-5xl">🤖</div>
                                    <h3 className="text-lg font-medium">Start a conversation</h3>
                                    <p className="text-sm text-muted-foreground">
                                        I can manage orders, read & modify code, and improve myself!
                                    </p>

                                    <div className="grid grid-cols-2 gap-2 mt-4">
                                        {quickActions.map((action, idx) => (
                                            <Button
                                                key={idx}
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleQuickAction(action.prompt)}
                                                className="text-xs flex items-center gap-2"
                                            >
                                                <action.icon className="h-3 w-3" />
                                                {action.label}
                                            </Button>
                                        ))}
                                    </div>

                                    <div className="mt-6 text-left space-y-3 border-t pt-4">
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground mb-1">📦 Order Management:</p>
                                            <div className="space-y-1 text-xs text-muted-foreground pl-3">
                                                <p>• "Create order for John, 2 iPhones, 240k"</p>
                                                <p>• "Show all pending orders"</p>
                                                <p>• "Update order JUMANJI-042 status to delivered"</p>
                                            </div>
                                        </div>
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground mb-1">💻 Code Access:</p>
                                            <div className="space-y-1 text-xs text-muted-foreground pl-3">
                                                <p>• "Show me the OrderChatController code"</p>
                                                <p>• "List all MCP tools"</p>
                                                <p>• "Where is CreateOrderTool used?"</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {messages.map((msg, i) => (
                            <div
                                key={i}
                                className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
                            >
                                <div
                                    className={`max-w-[80%] rounded-lg p-3 text-sm ${msg.role === 'user'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted'
                                        }`}
                                >
                                    {msg.role === 'user' ? (
                                        <div className="whitespace-pre-wrap break-words">
                                            {msg.content}
                                        </div>
                                    ) : (
                                        <div className="prose prose-sm dark:prose-invert max-w-none prose-p:my-2 prose-headings:my-2 prose-ul:my-2 prose-ol:my-2 prose-li:my-0.5 prose-table:text-xs prose-th:p-2 prose-td:p-2 prose-code:text-xs">
                                            <ReactMarkdown remarkPlugins={[remarkGfm]}>
                                                {msg.content}
                                            </ReactMarkdown>
                                        </div>
                                    )}
                                    {msg.role === 'ai' && renderToolResults(msg.toolResults)}
                                </div>
                            </div>
                        ))}

                        {loading && (
                            <div className="flex justify-start">
                                <div className="max-w-[80%] rounded-lg bg-muted p-3 text-sm">
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                        <span>AI is thinking...</span>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div ref={bottomRef} />
                    </div>
                </ScrollArea>

                <div className="mt-4 space-y-2">
                    <div className="flex gap-2">
                        <Input
                            placeholder={isListening ? "Listening..." : "Ask me anything about orders or code..."}
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault()
                                    sendMessage()
                                }
                            }}
                            disabled={loading || isListening}
                            className={`flex-1 ${isListening ? 'border-red-500 animate-pulse' : ''}`}
                        />
                        {isRecognitionSupported && (
                            <Button
                                onClick={toggleListening}
                                disabled={loading}
                                size="icon"
                                variant={isListening ? "destructive" : "outline"}
                                className={isListening ? "animate-pulse" : ""}
                            >
                                {isListening ? <MicOff className="h-4 w-4" /> : <Mic className="h-4 w-4" />}
                            </Button>
                        )}
                        <Button
                            onClick={sendMessage}
                            disabled={loading || !message.trim() || isListening}
                            size="icon"
                        >
                            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                        </Button>
                    </div>
                    <p className="text-xs text-muted-foreground text-center">
                        {isRecognitionSupported
                            ? "Enter to send • Shift+Enter for new line • 🎤 Voice • 💻 Code access"
                            : "Enter to send • Shift+Enter for new line • 💻 Code access enabled"
                        }
                    </p>
                </div>
            </div>
        </AppLayout>
    )
}