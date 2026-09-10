const fs = require('fs');

async function fetchCnh() {
  return fetch("https://wabi-brazil-south-api.analysis.windows.net/public/reports/querydata?synchronous=true", {
    "headers": {
      "accept": "application/json, text/plain, */*",
      "accept-language": "pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7,it;q=0.6",
      "activityid": "f8ddb668-3ac8-b7a8-c868-c656fa78b88a",
      "content-type": "application/json;charset=UTF-8",
      "requestid": "f126fe68-5ac8-9608-a044-48fce065a8dd",
      "sec-ch-ua": "\"Google Chrome\";v=\"143\", \"Chromium\";v=\"143\", \"Not A(Brand\";v=\"24\"",
      "sec-ch-ua-mobile": "?0",
      "sec-ch-ua-platform": "\"Windows\"",
      "sec-fetch-dest": "empty",
      "sec-fetch-mode": "cors",
      "sec-fetch-site": "cross-site",
      "x-powerbi-resourcekey": "a99cc6a6-d1a2-422f-981f-dc4ec861c584",
      "Referer": "https://www.gov.br/"
    },
    "body": "{\"version\":\"1.0.0\",\"queries\":[{\"Query\":{\"Commands\":[{\"SemanticQueryDataShapeCommand\":{\"Query\":{\"Version\":2,\"From\":[{\"Name\":\"i2\",\"Entity\":\"instrutores_RENACH\",\"Type\":0},{\"Name\":\"t\",\"Entity\":\"TOM_Serpro\",\"Type\":0}],\"Select\":[{\"Column\":{\"Expression\":{\"SourceRef\":{\"Source\":\"i2\"}},\"Property\":\"Nome\"},\"Name\":\"instrutores_RENACH.nome\",\"NativeReferenceName\":\"Nome\"},{\"Column\":{\"Expression\":{\"SourceRef\":{\"Source\":\"i2\"}},\"Property\":\"UF Detran\"},\"Name\":\"instrutores_RENACH.uf_detran_cadastramento\",\"NativeReferenceName\":\"UF Detran\"},{\"Column\":{\"Expression\":{\"SourceRef\":{\"Source\":\"i2\"}},\"Property\":\"Bairro\"},\"Name\":\"instrutores_RENACH.Bairro\",\"NativeReferenceName\":\"Bairro\"},{\"Column\":{\"Expression\":{\"SourceRef\":{\"Source\":\"t\"}},\"Property\":\"Município\"},\"Name\":\"TOM_Serpro.MUNI_NM\",\"NativeReferenceName\":\"Município\"}]},\"Binding\":{\"Primary\":{\"Groupings\":[{\"Projections\":[0,1,2,3],\"Subtotal\":1}]},\"DataReduction\":{\"DataVolume\":3,\"Primary\":{\"Window\":{\"Count\":500, \"RestartTokens\": [[\"'ADALBERTO JOSE SILVA ROCHA'\", \"'MG'\", \"'CRISTINA C'\", \"'SANTA LUZIA'\"]]}}},\"Version\":1},\"ExecutionMetricsKind\":1}}]},\"CacheKey\":\"{\\\"Commands\\\":[{\\\"SemanticQueryDataShapeCommand\\\":{\\\"Query\\\":{\\\"Version\\\":2,\\\"From\\\":[{\\\"Name\\\":\\\"i2\\\",\\\"Entity\\\":\\\"instrutores_RENACH\\\",\\\"Type\\\":0},{\\\"Name\\\":\\\"t\\\",\\\"Entity\\\":\\\"TOM_Serpro\\\",\\\"Type\\\":0}],\\\"Select\\\":[{\\\"Column\\\":{\\\"Expression\\\":{\\\"SourceRef\\\":{\\\"Source\\\":\\\"i2\\\"}},\\\"Property\\\":\\\"Nome\\\"},\\\"Name\\\":\\\"instrutores_RENACH.nome\\\",\\\"NativeReferenceName\\\":\\\"Nome\\\"},{\\\"Column\\\":{\\\"Expression\\\":{\\\"SourceRef\\\":{\\\"Source\\\":\\\"i2\\\"}},\\\"Property\\\":\\\"UF Detran\\\"},\\\"Name\\\":\\\"instrutores_RENACH.uf_detran_cadastramento\\\",\\\"NativeReferenceName\\\":\\\"UF Detran\\\"},{\\\"Column\\\":{\\\"Expression\\\":{\\\"SourceRef\\\":{\\\"Source\\\":\\\"i2\\\"}},\\\"Property\\\":\\\"Bairro\\\"},\\\"Name\\\":\\\"instrutores_RENACH.Bairro\\\",\\\"NativeReferenceName\\\":\\\"Bairro\\\"},{\\\"Column\\\":{\\\"Expression\\\":{\\\"SourceRef\\\":{\\\"Source\\\":\\\"t\\\"}},\\\"Property\\\":\\\"Município\\\"},\\\"Name\\\":\\\"TOM_Serpro.MUNI_NM\\\",\\\"NativeReferenceName\\\":\\\"Município\\\"}]},\\\"Binding\\\":{\\\"Primary\\\":{\\\"Groupings\\\":[{\\\"Projections\\\":[0,1,2,3],\\\"Subtotal\\\":1}]},\\\"DataReduction\\\":{\\\"DataVolume\\\":3,\\\"Primary\\\":{\\\"Window\\\":{\\\"Count\\\":500}}},\\\"Version\\\":1},\\\"ExecutionMetricsKind\\\":1}}]}\",\"QueryId\":\"\",\"ApplicationContext\":{\"DatasetId\":\"14a6e9a4-15a2-4016-8b0d-5d31f7a14359\",\"Sources\":[{\"ReportId\":\"0431059b-7d25-4d3c-8d89-eca91a99db93\",\"VisualId\":\"bec5c440e28ad1d253da\"}]}}],\"cancelQueries\":[],\"modelId\":10670397}",
    "method": "POST"
  }).then(response => response.json())
  .then(data => {
    // Salvar o JSON formatado
    fs.writeFileSync('response2.json', JSON.stringify(data, null, 2));
  })
  .catch(error => {
    console.error('Erro:', error);
  });
  console.log('123');
}

fetchCnh().then();