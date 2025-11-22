import React from "react";
import { Card, CardHeader, CardBody, Container, Row, Col, Table, Form, Input, Button } from "reactstrap";
import Header from "components/Headers/Header.js";

const Sistema = () => {
  const [users, setUsers] = React.useState([]);
  const [phones, setPhones] = React.useState([]);
  const [logs, setLogs] = React.useState([]);
  const [newUser, setNewUser] = React.useState({ nome:"", email:"", senha:"", role:"user", status:"ativo" });
  const [newPhone, setNewPhone] = React.useState({ entidade_tipo:"contato", entidade_id:"", tipo:"", telefone:"", whatsapp:false });
  const [logType, setLogType] = React.useState("logs");
  const loadUsers = async () => { try { const r = await fetch('/api/usuarios/list?limit=20'); const j = await r.json(); setUsers(j.items||[]); } catch(e){} };
  const loadPhones = async () => { try { const r = await fetch('/api/telefones/list?limit=20'); const j = await r.json(); setPhones(j.items||[]); } catch(e){} };
  const loadLogs = async () => { try { const r = await fetch(`/api/logs/list?type=${logType}&limit=20`); const j = await r.json(); setLogs(j.items||[]); } catch(e){} };
  const addUser = async () => { try { const r = await fetch('/api/usuarios/add',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(newUser) }); const j = await r.json(); if (j.ok) { setNewUser({ nome:"", email:"", senha:"", role:"user", status:"ativo" }); loadUsers(); } } catch(e){} };
  const addPhone = async () => { try { const r = await fetch('/api/telefones/add',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(newPhone) }); const j = await r.json(); if (j.ok) { setNewPhone({ entidade_tipo:"contato", entidade_id:"", tipo:"", telefone:"", whatsapp:false }); loadPhones(); } } catch(e){} };
  const deleteUser = async (id) => { try { const r = await fetch(`/api/usuarios/delete?id=${id}`,{ method:'POST' }); const j = await r.json(); if (j.ok) loadUsers(); } catch(e){} };
  const deletePhone = async (id) => { try { const r = await fetch(`/api/telefones/delete?id=${id}`,{ method:'POST' }); const j = await r.json(); if (j.ok) loadPhones(); } catch(e){} };
  React.useEffect(()=>{ loadUsers(); loadPhones(); loadLogs(); },[]);
  React.useEffect(()=>{ loadLogs(); },[logType]);
  return (
    <>
      <Header />
      <Container className="mt--7" fluid>
        <Row>
          <Col md="6">
            <Card className="shadow">
              <CardHeader className="border-0"><h3 className="mb-0">Usuários</h3></CardHeader>
              <CardBody>
                <Form className="mb-3" onSubmit={(e)=>{e.preventDefault(); addUser();}}>
                  <Row>
                    <Col md="4" className="mb-2"><Input placeholder="Nome" value={newUser.nome} onChange={(e)=>setNewUser({...newUser,nome:e.target.value})} /></Col>
                    <Col md="4" className="mb-2"><Input placeholder="Email" value={newUser.email} onChange={(e)=>setNewUser({...newUser,email:e.target.value})} /></Col>
                    <Col md="4" className="mb-2"><Input placeholder="Senha" type="password" value={newUser.senha} onChange={(e)=>setNewUser({...newUser,senha:e.target.value})} /></Col>
                    <Col md="4" className="mb-2"><Input placeholder="Role" value={newUser.role} onChange={(e)=>setNewUser({...newUser,role:e.target.value})} /></Col>
                    <Col md="4" className="mb-2"><Input placeholder="Status" value={newUser.status} onChange={(e)=>setNewUser({...newUser,status:e.target.value})} /></Col>
                    <Col md="4" className="mb-2"><Button color="primary" onClick={addUser}>Adicionar</Button></Col>
                  </Row>
                </Form>
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light"><tr><th>Nome</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
                  <tbody>{users.map(u=>(<tr key={u.id}><td>{u.nome}</td><td>{u.email}</td><td>{u.role}</td><td>{u.status}</td><td><Button size="sm" color="danger" onClick={()=>deleteUser(u.id)}>Excluir</Button></td></tr>))}</tbody>
                </Table>
              </CardBody>
            </Card>
          </Col>
          <Col md="6">
            <Card className="shadow">
              <CardHeader className="border-0"><h3 className="mb-0">Telefones</h3></CardHeader>
              <CardBody>
                <Form className="mb-3" onSubmit={(e)=>{e.preventDefault(); addPhone();}}>
                  <Row>
                    <Col md="3" className="mb-2"><Input placeholder="Entidade Tipo" value={newPhone.entidade_tipo} onChange={(e)=>setNewPhone({...newPhone,entidade_tipo:e.target.value})} /></Col>
                    <Col md="3" className="mb-2"><Input placeholder="Entidade ID" value={newPhone.entidade_id} onChange={(e)=>setNewPhone({...newPhone,entidade_id:e.target.value})} /></Col>
                    <Col md="2" className="mb-2"><Input placeholder="Tipo" value={newPhone.tipo} onChange={(e)=>setNewPhone({...newPhone,tipo:e.target.value})} /></Col>
                    <Col md="2" className="mb-2"><Input placeholder="Telefone" value={newPhone.telefone} onChange={(e)=>setNewPhone({...newPhone,telefone:e.target.value})} /></Col>
                    <Col md="2" className="mb-2"><Button color="primary" onClick={addPhone}>Adicionar</Button></Col>
                  </Row>
                </Form>
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light"><tr><th>Tipo</th><th>Telefone</th><th>WhatsApp</th><th>Entidade</th><th></th></tr></thead>
                  <tbody>{phones.map(p=>(<tr key={p.id}><td>{p.tipo||"-"}</td><td>{p.telefone}</td><td>{p.whatsapp?"Sim":"Não"}</td><td>{p.entidade_tipo||"-"} #{p.entidade_id||"-"}</td><td><Button size="sm" color="danger" onClick={()=>deletePhone(p.id)}>Excluir</Button></td></tr>))}</tbody>
                </Table>
              </CardBody>
            </Card>
          </Col>
        </Row>
        <Row className="mt-4">
          <Col>
            <Card className="shadow">
              <CardHeader className="border-0"><h3 className="mb-0">Logs</h3></CardHeader>
              <CardBody>
                <Row className="mb-3">
                  <Col md="3"><Input type="select" value={logType} onChange={(e)=>setLogType(e.target.value)}><option value="logs">Logs</option><option value="automacao">Automação</option><option value="crawler">Crawler</option></Input></Col>
                </Row>
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light"><tr><th>Nome</th><th>Nível</th><th>Mensagem</th><th>Criado</th></tr></thead>
                  <tbody>{logs.map(l=>(<tr key={l.id}><td>{l.name}</td><td>{l.level||"-"}</td><td>{l.message||"-"}</td><td>{new Date(l.created_at).toLocaleString()}</td></tr>))}</tbody>
                </Table>
              </CardBody>
            </Card>
          </Col>
        </Row>
      </Container>
    </>
  );
};

export default Sistema;
